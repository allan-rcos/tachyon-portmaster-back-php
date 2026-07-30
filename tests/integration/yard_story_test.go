package integration

import (
	"net/http"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"portmaster/tests/integration/internal/factories"
	"portmaster/tests/integration/internal/fbs"
)

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

		got := decodeRoot(t, requireOK(t, c.Get(t, "/products/"+productID)).Body, fbs.GetRootAsProductResponse)
		assert.Equal(t, productID, string(got.Id()))

		assert.Equal(t, http.StatusNotFound, c.Get(t, "/products/P0000000").Status,
			"an unknown product id must be a 404, not an empty 200")

		list := decodeRoot(t, requireOK(t, c.Get(t, "/products")).Body, fbs.GetRootAsProductListResponse)
		assert.GreaterOrEqual(t, list.Total(), int32(1))

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
		requireOK(t, c.Post(t, "/manifests/load-item", factories.LoadItem(containerID, productID, 200)))

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

		metrics := decodeRoot(t, requireOK(t, c.Get(t, "/metrics")).Body, fbs.GetRootAsMetricsResponse)
		assert.GreaterOrEqual(t, metrics.TotalContainers(), int32(1))
		assert.GreaterOrEqual(t, metrics.RegisteredProducts(), int32(1))
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
