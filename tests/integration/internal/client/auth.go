package client

import (
	"net/http"
	"testing"

	flatbuffers "github.com/google/flatbuffers/go"

	"portmaster/tests/integration/internal/fbs"
)

// Setup performs a FlatBuffers POST /setup, creating the first user of a fresh
// environment and logging them in. The session cookies are retained by the
// client's jar for later calls.
//
// This is the only way a reset environment gets a user: every other route that
// creates one sits behind a permission that nobody holds yet.
func Setup(t *testing.T, c *Client, name, email, password string) *fbs.LoginResponse {
	t.Helper()

	b := flatbuffers.NewBuilder(0)
	nameOff := b.CreateString(name)
	emailOff := b.CreateString(email)
	passOff := b.CreateString(password)
	fbs.SetupRequestStart(b)
	fbs.SetupRequestAddName(b, nameOff)
	fbs.SetupRequestAddEmail(b, emailOff)
	fbs.SetupRequestAddPassword(b, passOff)
	b.Finish(fbs.SetupRequestEnd(b))

	resp := c.Post(t, "/setup", b.FinishedBytes())
	if resp.Status != http.StatusCreated {
		t.Fatalf("setup as %s: status %d", email, resp.Status)
	}

	return loginResponse(t, resp)
}

// LoginAs performs a FlatBuffers /auth/login and returns the decoded response.
// The auth/refresh cookies are retained by the client's jar for later calls.
func LoginAs(t *testing.T, c *Client, email, password string) *fbs.LoginResponse {
	t.Helper()

	b := flatbuffers.NewBuilder(0)
	emailOff := b.CreateString(email)
	passOff := b.CreateString(password)
	fbs.LoginRequestStart(b)
	fbs.LoginRequestAddEmail(b, emailOff)
	fbs.LoginRequestAddPassword(b, passOff)
	b.Finish(fbs.LoginRequestEnd(b))

	resp := c.Post(t, "/auth/login", b.FinishedBytes())
	if resp.Status != http.StatusOK {
		t.Fatalf("login as %s: status %d", email, resp.Status)
	}

	return loginResponse(t, resp)
}

// SetupBody builds a POST /setup payload without sending it — for the case a
// test needs to drive the response itself (asserting the 409, for instance).
func SetupBody(name, email, password string) []byte {
	b := flatbuffers.NewBuilder(0)
	nameOff := b.CreateString(name)
	emailOff := b.CreateString(email)
	passOff := b.CreateString(password)
	fbs.SetupRequestStart(b)
	fbs.SetupRequestAddName(b, nameOff)
	fbs.SetupRequestAddEmail(b, emailOff)
	fbs.SetupRequestAddPassword(b, passOff)
	b.Finish(fbs.SetupRequestEnd(b))
	return b.FinishedBytes()
}

// loginResponse decodes a session body, failing rather than panicking when the
// response is too short to hold a FlatBuffers root — a panic here would take the
// whole test binary down with it.
func loginResponse(t *testing.T, resp Response) *fbs.LoginResponse {
	t.Helper()
	if len(resp.Body) < 8 {
		t.Fatalf("session response too short to be a FlatBuffers root: %d bytes", len(resp.Body))
	}
	return fbs.GetRootAsLoginResponse(resp.Body, 0)
}
