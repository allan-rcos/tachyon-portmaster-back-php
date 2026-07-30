package factories

import (
	"encoding/json"

	"github.com/brianvoe/gofakeit/v7"
	flatbuffers "github.com/google/flatbuffers/go"

	"portmaster/tests/integration/internal/fbs"
)

// User is a generated user-create payload plus its values.
type User struct {
	Name     string
	Email    string
	Password string
	RoleIDs  []string
	Bytes    []byte
}

// NewUser builds a POST /users body assigning the given role ids.
func NewUser(roleIDs ...string) User {
	name := gofakeit.Name()
	email := gofakeit.Email()
	password := gofakeit.Password(true, true, true, false, false, 12)

	b := flatbuffers.NewBuilder(0)
	nameOff := b.CreateString(name)
	emailOff := b.CreateString(email)
	passOff := b.CreateString(password)
	roleOffsets := make([]flatbuffers.UOffsetT, len(roleIDs))
	for i, r := range roleIDs {
		roleOffsets[i] = b.CreateString(r)
	}
	fbs.UserCreateRequestStartRoleIdsVector(b, len(roleOffsets))
	for i := len(roleOffsets) - 1; i >= 0; i-- {
		b.PrependUOffsetT(roleOffsets[i])
	}
	rolesVec := b.EndVector(len(roleOffsets))

	fbs.UserCreateRequestStart(b)
	fbs.UserCreateRequestAddName(b, nameOff)
	fbs.UserCreateRequestAddEmail(b, emailOff)
	fbs.UserCreateRequestAddInitialPassword(b, passOff)
	fbs.UserCreateRequestAddRoleIds(b, rolesVec)
	b.Finish(fbs.UserCreateRequestEnd(b))

	return User{Name: name, Email: email, Password: password, RoleIDs: roleIDs, Bytes: b.FinishedBytes()}
}

// UserUpdate builds a PUT /users/{id} body.
func UserUpdate(name, email string) []byte {
	b := flatbuffers.NewBuilder(0)
	nameOff := b.CreateString(name)
	emailOff := b.CreateString(email)
	fbs.UserUpdateRequestStart(b)
	fbs.UserUpdateRequestAddName(b, nameOff)
	fbs.UserUpdateRequestAddEmail(b, emailOff)
	b.Finish(fbs.UserUpdateRequestEnd(b))
	return b.FinishedBytes()
}

// PasswordReset builds a PUT /users/{id}/password body — the administrator's
// reset, which needs no current password, unlike PasswordChange.
func PasswordReset(newPassword string) []byte {
	b := flatbuffers.NewBuilder(0)
	pwOff := b.CreateString(newPassword)
	fbs.UserAdminPasswordResetRequestStart(b)
	fbs.UserAdminPasswordResetRequestAddNewPassword(b, pwOff)
	b.Finish(fbs.UserAdminPasswordResetRequestEnd(b))
	return b.FinishedBytes()
}

// RoleIDs builds the body for PUT /users/{id}/roles.
//
// That endpoint is the one place the API still parses an inline JSON object
// (`{"role_ids": [...]}`) instead of a FlatBuffers table — it has no schema in
// the published contract. The factory mirrors that shape verbatim so the test
// exercises the endpoint as it actually is, not as it ought to be.
func RoleIDs(ids ...string) []byte {
	payload, err := json.Marshal(map[string][]string{"role_ids": ids})
	if err != nil {
		panic(err)
	}
	return payload
}

// UserWithEmail builds a POST /users body with a chosen e-mail and password, to
// exercise the uniqueness and password-strength rules.
func UserWithEmail(email, password string) []byte {
	b := flatbuffers.NewBuilder(0)
	nameOff := b.CreateString(gofakeit.Name())
	emailOff := b.CreateString(email)
	passOff := b.CreateString(password)
	fbs.UserCreateRequestStartRoleIdsVector(b, 0)
	rolesVec := b.EndVector(0)
	fbs.UserCreateRequestStart(b)
	fbs.UserCreateRequestAddName(b, nameOff)
	fbs.UserCreateRequestAddEmail(b, emailOff)
	fbs.UserCreateRequestAddInitialPassword(b, passOff)
	fbs.UserCreateRequestAddRoleIds(b, rolesVec)
	b.Finish(fbs.UserCreateRequestEnd(b))
	return b.FinishedBytes()
}
