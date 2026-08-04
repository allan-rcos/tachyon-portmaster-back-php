package integration

import (
	"net/http"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"portmaster/tests/integration/internal/fbs"
)

// TestVersionRouting pins how the router answers the three path spaces a caller
// can ask for: the version it means, the version it left out, and one that does
// not exist.
//
// The unit test already proves the merge rule against synthetic versions. What
// only a real process can prove is that the alias survives everything in front
// of the dispatcher — negotiation, authentication, the cookie that has to match
// on both paths — which is exactly what breaks when a prefix is bolted on at the
// wrong layer.
func TestVersionRouting(t *testing.T) {
	t.Parallel()
	_, c := adminSession(t)

	// Same session, same jar, no version in the path.
	root := c.Unversioned()

	t.Run("the same route answers with and without the version", func(t *testing.T) {
		versioned := decodeRoot(t, requireOK(t, c.Get(t, "/info")).Body, fbs.GetRootAsProjectInfo)
		unversioned := decodeRoot(t, requireOK(t, root.Get(t, "/info")).Body, fbs.GetRootAsProjectInfo)

		assert.NotEmpty(t, string(versioned.Name()))
		assert.Equal(t, string(versioned.Name()), string(unversioned.Name()))
		assert.Equal(t, string(versioned.Version()), string(unversioned.Version()))
	})

	// The alias is a route, not a redirect: the session cookie set through the
	// versioned path has to be honoured on the unversioned one, or half the API
	// would answer 401 to an authenticated caller.
	t.Run("an authenticated route answers unversioned too", func(t *testing.T) {
		require.Equal(t, http.StatusOK, c.Get(t, "/products").Status)
		require.Equal(t, http.StatusOK, root.Get(t, "/products").Status)
	})

	t.Run("a version that does not exist is not found", func(t *testing.T) {
		resp := root.Get(t, "/v9/info")
		assert.Equal(t, http.StatusNotFound, resp.Status)
	})

	// /api belongs to the reverse proxy and is stripped before the request gets
	// here; the server-side renderer never had it. Answering to it would mean
	// the browser needing /api/api and the renderer breaking outright.
	t.Run("the proxy mount point is not served", func(t *testing.T) {
		assert.Equal(t, http.StatusNotFound, root.Get(t, "/api/v1/info").Status)
	})
}
