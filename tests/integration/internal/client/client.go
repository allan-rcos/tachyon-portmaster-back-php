// Package client is a thin FlatBuffers-over-HTTP driver: it sends and expects
// application/x-flatbuffers, and keeps a cookie jar so the auth/refresh cookies
// set by /auth/login ride along on subsequent requests.
package client

import (
	"bytes"
	"io"
	"net/http"
	"net/http/cookiejar"
	"net/url"
	"testing"
	"time"
)

const contentType = "application/x-flatbuffers"

// jsonContentType is what a client asks for when it wants the JSON side of the
// same contract. Used only by the negotiation test — every other request in the
// suite is binary, which is the wire the front end actually speaks.
const jsonContentType = "application/json"

// The two halves of what sits between the base URL and a path, mirroring the
// back end: apiPrefix is where the whole API is mounted (empty — it is the root
// of the process), apiVersion is the contract version. Applied here, once, so
// the paths in the tests and factories read exactly as they do in swagger.json,
// which lists them relative to a server base that already ends in /v1.
const (
	apiPrefix  = ""
	apiVersion = "/v1"
)

// Client drives one API environment over the FlatBuffers wire.
type Client struct {
	baseURL string
	prefix  string
	accept  string
	http    *http.Client
}

// Response is a decoded HTTP response: status, the raw body, and the media type
// the server said the body is. The last one is what proves negotiation happened
// rather than merely not failing.
type Response struct {
	Status      int
	ContentType string
	Body        []byte
}

// New returns a client for the given base URL with its own cookie jar. It asks
// for the current contract version explicitly, which is what a real client
// should do.
func New(baseURL string) *Client {
	jar, _ := cookiejar.New(nil)
	return &Client{
		baseURL: baseURL,
		prefix:  apiPrefix + apiVersion,
		accept:  contentType,
		http:    &http.Client{Jar: jar, Timeout: 20 * time.Second},
	}
}

// Unversioned returns a view of the same session that drops the version from the
// path, for exercising the router's root alias. It shares the http client, and
// therefore the cookie jar: a session logged in through one is logged in through
// the other.
func (c *Client) Unversioned() *Client {
	return &Client{
		baseURL: c.baseURL,
		prefix:  apiPrefix,
		accept:  c.accept,
		http:    c.http,
	}
}

// AsJSON returns a view of the same session that asks for JSON back. Like
// Unversioned it shares the http client and therefore the cookie jar, so the
// same authenticated caller can be answered in either format.
func (c *Client) AsJSON() *Client {
	return &Client{
		baseURL: c.baseURL,
		prefix:  c.prefix,
		accept:  jsonContentType,
		http:    c.http,
	}
}

func (c *Client) do(t *testing.T, method, path string, body []byte) Response {
	t.Helper()

	var reader io.Reader
	if body != nil {
		reader = bytes.NewReader(body)
	}

	req, err := http.NewRequest(method, c.baseURL+c.prefix+path, reader)
	if err != nil {
		t.Fatalf("build request %s %s: %v", method, path, err)
	}
	req.Header.Set("Accept", c.accept)
	if body != nil {
		// The request body stays binary even when the answer is asked for in
		// JSON: the two headers are negotiated independently.
		req.Header.Set("Content-Type", contentType)
	}

	resp, err := c.http.Do(req)
	if err != nil {
		t.Fatalf("%s %s: %v", method, path, err)
	}
	defer resp.Body.Close()

	data, err := io.ReadAll(resp.Body)
	if err != nil {
		t.Fatalf("read body %s %s: %v", method, path, err)
	}

	return Response{
		Status:      resp.StatusCode,
		ContentType: resp.Header.Get("Content-Type"),
		Body:        data,
	}
}

// Cookie returns the value of a cookie currently held in the jar, or "" when it
// is not set. Used by tests that need to hold on to a token the server rotated
// away, in order to prove the old one no longer works.
func (c *Client) Cookie(t *testing.T, name string) string {
	t.Helper()

	u, err := url.Parse(c.baseURL)
	if err != nil {
		t.Fatalf("parse base url: %v", err)
	}

	for _, ck := range c.http.Jar.Cookies(u) {
		if ck.Name == name {
			return ck.Value
		}
	}
	return ""
}

// SetCookie overwrites a cookie in the jar, letting a test present a value the
// server would never have sent at that point — a consumed refresh token, or an
// access token in the refresh slot.
func (c *Client) SetCookie(t *testing.T, name, value string) {
	t.Helper()

	u, err := url.Parse(c.baseURL)
	if err != nil {
		t.Fatalf("parse base url: %v", err)
	}

	c.http.Jar.SetCookies(u, []*http.Cookie{{Name: name, Value: value, Path: "/"}})
}

func (c *Client) Get(t *testing.T, path string) Response {
	return c.do(t, http.MethodGet, path, nil)
}

func (c *Client) Post(t *testing.T, path string, body []byte) Response {
	return c.do(t, http.MethodPost, path, body)
}

func (c *Client) Put(t *testing.T, path string, body []byte) Response {
	return c.do(t, http.MethodPut, path, body)
}

func (c *Client) Delete(t *testing.T, path string) Response {
	return c.do(t, http.MethodDelete, path, nil)
}
