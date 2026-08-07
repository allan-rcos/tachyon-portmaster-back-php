package integration

import (
	"encoding/json"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"portmaster/tests/integration/internal/fbs"
)

// TestContentNegotiation pins the one thing the unit tests cannot: that the
// strategy the middleware picked from the headers is the strategy the response
// was actually rendered with, all the way out through the real process.
//
// The interesting half is the error. A problem document used to be JSON no
// matter what the caller asked for; it is a table of the published schema like
// any other, so it now follows `Accept` like any other — and this suite, which
// asks for binary on every request, is exactly the client that would have kept
// receiving JSON without noticing.
func TestContentNegotiation(t *testing.T) {
	t.Parallel()
	_, c := adminSession(t)

	readableClient := c.AsJSON()

	t.Run("a success comes back in the format that was asked for", func(t *testing.T) {
		binary := requireOK(t, c.Get(t, "/info"))
		readable := requireOK(t, readableClient.Get(t, "/info"))

		assert.Contains(t, binary.ContentType, "application/x-flatbuffers")
		assert.Contains(t, readable.ContentType, "application/json")

		// Same endpoint, same session, two encodings of the same message.
		fromFbs := decodeRoot(t, binary.Body, fbs.GetRootAsProjectInfo)
		fromJSON := decodeProjectInfoJSON(t, readable.Body)

		assert.NotEmpty(t, string(fromFbs.Name()))
		assert.Equal(t, string(fromFbs.Name()), fromJSON.Name)
		assert.Equal(t, string(fromFbs.Version()), fromJSON.Version)
	})

	t.Run("an error comes back in the format that was asked for too", func(t *testing.T) {
		// A route that exists but an id that does not: a 404 raised by the
		// controller, so it goes through the same accepts strategy as a success.
		missing := "/products/zzzzzzzz"

		binary := c.Get(t, missing)
		require.Equal(t, 404, binary.Status)
		assert.Contains(t, binary.ContentType, "application/x-flatbuffers")

		problem := decodeRoot(t, binary.Body, fbs.GetRootAsProblemDetails)
		assert.Equal(t, int32(404), problem.Status())
		assert.Equal(t, "Not Found", string(problem.Title()))

		readable := readableClient.Get(t, missing)
		require.Equal(t, 404, readable.Status)

		// RFC 7807 keeps its own media type on the JSON branch, and clients
		// switch on it — plain application/json here would be a regression.
		assert.Contains(t, readable.ContentType, "application/problem+json")
		assert.Contains(t, string(readable.Body), `"status":404`)
	})
}

// projectInfoJSON is the JSON face of the same table `/info` answers with,
// keyed by the schema's field names rather than the PHP property names.
type projectInfoJSON struct {
	Name    string `json:"name"`
	Version string `json:"version"`
}

func decodeProjectInfoJSON(t *testing.T, body []byte) projectInfoJSON {
	t.Helper()
	var out projectInfoJSON
	if err := json.Unmarshal(body, &out); err != nil {
		t.Fatalf("decode /info as JSON: %v (body %q)", err, string(body))
	}
	return out
}
