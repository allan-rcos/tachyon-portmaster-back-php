package integration

import (
	"math"
	"net/http"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"portmaster/tests/integration/internal/factories"
	"portmaster/tests/integration/internal/fbs"
)

// sealFillRatio mirrors ContainerTM::MIN_SEAL_FILL_RATIO — the share of a
// container's capacity that has to be filled before it may be sealed. Kept here
// so the story can size a load against the rule instead of guessing at it; if
// the domain moves the floor, this fails loudly rather than silently testing
// nothing.
const sealFillRatio = 0.10

// TestYardStory follows one container from an empty yard through loading,
// sealing and dispatch, checking at each step both what is allowed and what the
// domain refuses.
//
// Grouped as a story because the interesting rules are *transitions*: a
// container cannot be sealed until it is loaded, cannot be loaded once sealed,
// cannot be dispatched twice. Testing those one endpoint at a time would mean
// rebuilding the same state repeatedly — and would never exercise the ordering
// that the rules are about.
func TestYardStory(t *testing.T) {
	t.Parallel()
	_, c := adminSession(t)

	var (
		product      factories.Product
		productID    string
		container    factories.Container
		container2ID string
		containerID  string
	)

	t.Run("products are catalogued, and invalid ones refused", func(t *testing.T) {
		assert.Equal(t, http.StatusUnprocessableEntity,
			c.Post(t, "/products", factories.InvalidProduct()).Status,
			"a product with no name must be rejected by the table module")

		product = factories.NewProduct()
		created := decodeRoot(t, requireOK(t, c.Post(t, "/products", product.Bytes)).Body, fbs.GetRootAsProductResponse)
		productID = string(created.Id())
		require.NotEmpty(t, productID)
		assert.Equal(t, product.Name, string(created.Name()))

		// A read by id is deliberately not cached, so it must never carry the
		// header — not even when repeated, which is when a listing would start
		// hitting. This is the other half of the assertion: the suite has to
		// catch caching where none was intended just as much as the reverse.
		byID := requireCacheMiss(t, requireOK(t, c.Get(t, "/products/"+productID)),
			"a read by id is not cached")
		got := decodeRoot(t, byID.Body, fbs.GetRootAsProductResponse)
		assert.Equal(t, productID, string(got.Id()))

		requireCacheMiss(t, requireOK(t, c.Get(t, "/products/"+productID)),
			"repeating a read by id must still not be cached")

		assert.Equal(t, http.StatusNotFound, c.Get(t, "/products/P0000000").Status,
			"an unknown product id must be a 404, not an empty 200")

		// First read of this listing on a fresh environment, so it is computed;
		// the immediate repeat must then come from the cache. The pair is what
		// proves the cache is storing at all — every other assertion in this
		// suite passes just as well against a cache that never stores anything.
		first := requireCacheMiss(t, requireOK(t, c.Get(t, "/products")),
			"the first read of a listing cannot be a hit")
		list := decodeRoot(t, first.Body, fbs.GetRootAsProductListResponse)
		assert.GreaterOrEqual(t, list.Total(), int32(1))

		requireCacheHit(t, requireOK(t, c.Get(t, "/products")),
			"an immediate repeat of the same listing must be served from the cache")

		_, update := factories.ProductUpdate()
		requireOK(t, c.Put(t, "/products/"+productID, update))
	})

	t.Run("containers are registered, and their codes are unique", func(t *testing.T) {
		container = factories.NewContainer()
		created := decodeRoot(t, requireOK(t, c.Post(t, "/containers", container.Bytes)).Body, fbs.GetRootAsContainerResponse)
		containerID = string(created.Id())
		require.NotEmpty(t, containerID)
		assert.Equal(t, container.Code, string(created.Code()))
		assert.Equal(t, fbs.ContainerStatusEmpty, created.Status(), "a fresh container starts empty")

		duplicate := c.Post(t, "/containers", factories.ContainerWithCode(container.Code, 1000))
		assert.GreaterOrEqual(t, duplicate.Status, 400, "a container code must be unique")

		assert.Equal(t, http.StatusNotFound, c.Get(t, "/containers/P0000000").Status)

		list := decodeRoot(t, requireOK(t, c.Get(t, "/containers")).Body, fbs.GetRootAsContainerListResponse)
		assert.GreaterOrEqual(t, list.Total(), int32(1))

		requireOK(t, c.Put(t, "/containers/"+containerID, factories.ContainerUpdate(1500)))
	})

	t.Run("an empty container cannot be sealed", func(t *testing.T) {
		resp := c.Post(t, "/containers/"+containerID+"/seal", nil)
		assert.Equal(t, http.StatusConflict, resp.Status,
			"two rules refuse this and either answer is legitimate: the status is "+
				"not loading, and the container is under the 10% floor")
	})

	t.Run("loading enforces quantity and capacity", func(t *testing.T) {
		assert.Equal(t, http.StatusUnprocessableEntity,
			c.Post(t, "/manifests/load-item", factories.LoadItem(containerID, productID, 0)).Status,
			"quantity must be greater than zero")

		assert.Equal(t, http.StatusConflict,
			c.Post(t, "/manifests/load-item", factories.LoadItem(containerID, productID, 1_000_000)).Status,
			"loading beyond the container capacity must be refused")

		loaded := decodeRoot(t,
			requireOK(t, c.Post(t, "/manifests/load-item", factories.LoadItem(containerID, productID, 25))).Body,
			fbs.GetRootAsManifestResponse)
		require.NotNil(t, loaded.Container(nil))
		assert.Equal(t, fbs.ContainerStatusLoading, loaded.Container(nil).Status(),
			"loading moves the container out of empty")
	})

	t.Run("unloading cannot take out more than went in", func(t *testing.T) {
		assert.Equal(t, http.StatusConflict,
			c.Post(t, "/manifests/unload-item", factories.UnloadItem(containerID, productID, 10_000)).Status,
			"unloading more than is loaded must be refused")

		requireOK(t, c.Post(t, "/manifests/unload-item", factories.UnloadItem(containerID, productID, 5)))
	})

	t.Run("a container seals only once it is full enough, then dispatches once", func(t *testing.T) {
		// Below the 10% floor the seal is refused; topping it up allows it.
		//
		// The top-up is computed from what the server actually holds instead of
		// being a fixed quantity. The floor is a fraction of the container's
		// capacity but a load is measured in units of a product whose density
		// the factory randomises, so no constant clears the floor for every
		// draw — a light enough product left the container short and the seal
		// answered 409, failing roughly one run in eight over an arithmetic
		// accident rather than over the rule being tested.
		state := decodeRoot(t, requireOK(t, c.Get(t, "/containers/"+containerID)).Body, fbs.GetRootAsContainerResponse)
		stored := decodeRoot(t, requireOK(t, c.Get(t, "/products/"+productID)).Body, fbs.GetRootAsProductResponse)

		missing := sealFillRatio*state.MaxCapacity() - state.CurrentWeight()
		require.Positive(t, missing, "the container must still be under the floor before it is topped up")
		require.Positive(t, stored.Density(), "a product without density makes the top-up unanswerable")

		// One unit over, so that rounding cannot land a hair under a floor the
		// domain compares with a strict less-than.
		topUp := math.Ceil(missing/stored.Density()) + 1
		requireOK(t, c.Post(t, "/manifests/load-item", factories.LoadItem(containerID, productID, topUp)))

		requireNoContent(t, c.Post(t, "/containers/"+containerID+"/seal", nil))
		sealed := decodeRoot(t, requireOK(t, c.Get(t, "/containers/"+containerID)).Body, fbs.GetRootAsContainerResponse)
		assert.Equal(t, fbs.ContainerStatusSealed, sealed.Status())

		assert.Equal(t, http.StatusConflict,
			c.Post(t, "/manifests/load-item", factories.LoadItem(containerID, productID, 1)).Status,
			"a sealed container takes no more cargo")

		assert.Equal(t, http.StatusConflict, c.Post(t, "/containers/"+containerID+"/seal", nil).Status,
			"sealing an already sealed container is a conflict")

		requireNoContent(t, c.Post(t, "/containers/"+containerID+"/dispatch", nil))
		dispatched := decodeRoot(t, requireOK(t, c.Get(t, "/containers/"+containerID)).Body, fbs.GetRootAsContainerResponse)
		assert.Equal(t, fbs.ContainerStatusInTransit, dispatched.Status())

		assert.Equal(t, http.StatusConflict, c.Post(t, "/containers/"+containerID+"/dispatch", nil).Status,
			"only a sealed container can be dispatched")
	})

	t.Run("the yard reports what it holds", func(t *testing.T) {
		requireOK(t, c.Get(t, "/containers/summary"))

		// Narrowed by id, because the summary of every container in a yard that
		// other stories are also filling says nothing about this one's history.
		summary := decodeRoot(t,
			requireOK(t, c.Get(t, "/containers/summary?id="+containerID)).Body,
			fbs.GetRootAsContainerSummaryListResponse)
		require.Equal(t, 1, summary.DataLength(), "an id narrows the page to that one container")

		var item fbs.ContainerSummaryResponse
		require.True(t, summary.Data(&item, 0))
		require.GreaterOrEqual(t, item.RecentLogsLength(), 3,
			"two loads and one unload each leave a telemetry entry behind")

		// The event is an enum on the wire, so the log reads back as the same
		// vocabulary the schema publishes rather than as free text.
		seen := map[fbs.TelemetryEvent]bool{}
		for i := 0; i < item.RecentLogsLength(); i++ {
			var log fbs.TelemetryLogItem
			require.True(t, item.RecentLogs(&log, i))
			require.Contains(t,
				[]fbs.TelemetryEvent{fbs.TelemetryEventLoad, fbs.TelemetryEventUnload},
				log.Event(), "every entry must carry a declared event")
			seen[log.Event()] = true
		}
		assert.True(t, seen[fbs.TelemetryEventLoad] && seen[fbs.TelemetryEventUnload],
			"the log must record both what went in and what came out")

		metrics := decodeRoot(t, requireOK(t, c.Get(t, "/metrics")).Body, fbs.GetRootAsMetricsResponse)
		assert.GreaterOrEqual(t, metrics.TotalContainers(), int32(1))
		assert.GreaterOrEqual(t, metrics.RegisteredProducts(), int32(1))
	})

	t.Run("a write is visible to the next read, on whichever worker serves it", func(t *testing.T) {
		// The listings are cached, and the cache is what this sub-test is about.
		//
		// The API container runs APP_WORKER_NUM workers and the object graph is
		// built per worker, after the fork. An in-process cache would therefore
		// be one cache per worker, and a write handled by worker 1 would leave
		// worker 2 serving the page it had already cached. Entries live in an
		// ENGINE=MEMORY table precisely so that cannot happen.
		//
		// Two things make that observable rather than lucky, and both are
		// load-bearing:
		//
		//   * the reads reconnect. The shared client keeps its connection alive,
		//     and OpenSwoole hands one connection to one worker for its whole
		//     life, so a keep-alive read is answered by whichever worker handled
		//     the write — which a per-worker cache would also get right.
		//   * they repeat. Which worker takes a fresh connection is not ours to
		//     choose, so one read proves nothing; several make it improbable
		//     that none of them lands elsewhere.
		const reads = 8

		cc := c.WithoutKeepAlive()

		before := decodeRoot(t, requireOK(t, cc.Get(t, "/products")).Body, fbs.GetRootAsProductListResponse)
		beforeTotal := before.Total()

		fresh := factories.NewProduct()
		created := decodeRoot(t, requireOK(t, c.Post(t, "/products", fresh.Bytes)).Body, fbs.GetRootAsProductResponse)
		freshID := string(created.Id())
		require.NotEmpty(t, freshID)

		for i := 0; i < reads; i++ {
			resp := requireOK(t, cc.Get(t, "/products"))

			// The first read after the write must be computed on whichever
			// worker takes it — the write dropped the group, so there is nothing
			// to hit. Later reads may legitimately hit the entry this loop is
			// itself repopulating, so only the body is checked from then on.
			if i == 0 {
				requireCacheMiss(t, resp, "the write must have dropped the cached page")
			}

			list := decodeRoot(t, resp.Body, fbs.GetRootAsProductListResponse)
			require.Greater(t, list.Total(), beforeTotal,
				"read %d still reports the total from before the write: the write did not "+
					"invalidate the cache every worker reads", i+1)
		}

		// The same property on a state transition rather than an insertion: a
		// container that has been sealed must not still list as loading.
		made := decodeRoot(t, requireOK(t, c.Post(t, "/containers", factories.NewContainer().Bytes)).Body,
			fbs.GetRootAsContainerResponse)
		sealTargetID := string(made.Id())

		// Populate the listing cache *before* the transition, so the read below
		// is answering from an entry the seal has to have dropped.
		requireOK(t, cc.Get(t, "/containers"))

		// Loaded past the seal floor, computed from what the server holds for
		// the same reason the sub-test above does it: the product's density is
		// randomised, so no constant clears the floor on every draw.
		stored := decodeRoot(t, requireOK(t, c.Get(t, "/products/"+productID)).Body, fbs.GetRootAsProductResponse)
		require.Positive(t, stored.Density(), "a product without density makes the load unanswerable")

		enough := math.Ceil(sealFillRatio*made.MaxCapacity()/stored.Density()) + 1
		requireOK(t, c.Post(t, "/manifests/load-item", factories.LoadItem(sealTargetID, productID, enough)))
		requireNoContent(t, c.Post(t, "/containers/"+sealTargetID+"/seal", nil))

		for i := 0; i < reads; i++ {
			resp := requireOK(t, cc.Get(t, "/containers"))
			if i == 0 {
				requireCacheMiss(t, resp, "sealing must have dropped the cached container page")
			}

			list := decodeRoot(t, resp.Body, fbs.GetRootAsContainerListResponse)

			var found bool
			for j := 0; j < list.DataLength(); j++ {
				var item fbs.ContainerResponse
				require.True(t, list.Data(&item, j))
				if string(item.Id()) != sealTargetID {
					continue
				}
				found = true
				require.Equal(t, fbs.ContainerStatusSealed, item.Status(),
					"read %d lists the container as unsealed after it was sealed", i+1)
			}

			require.True(t, found, "read %d does not list the container at all", i+1)
		}
	})

	t.Run("containers and products can be retired", func(t *testing.T) {
		// A second, untouched container is the one safe to delete: the first is
		// in transit with cargo against it.
		spare := factories.NewContainer()
		created := decodeRoot(t, requireOK(t, c.Post(t, "/containers", spare.Bytes)).Body, fbs.GetRootAsContainerResponse)
		container2ID = string(created.Id())

		requireOK(t, c.Delete(t, "/containers/"+container2ID))
		assert.Equal(t, http.StatusNotFound, c.Get(t, "/containers/"+container2ID).Status,
			"a deleted container must stop resolving")

		spareProduct := factories.NewProduct()
		p := decodeRoot(t, requireOK(t, c.Post(t, "/products", spareProduct.Bytes)).Body, fbs.GetRootAsProductResponse)
		requireOK(t, c.Delete(t, "/products/"+string(p.Id())))
		assert.Equal(t, http.StatusNotFound, c.Get(t, "/products/"+string(p.Id())).Status)
	})
}
