// Package factories builds FlatBuffers request payloads for the integration
// suite, filling them with fake-but-plausible data (gofakeit). Each factory
// returns both the encoded bytes and the values used, so a test can create a
// resource and then assert the server echoed those values back.
package factories

import (
	"encoding/json"
	"fmt"

	"github.com/brianvoe/gofakeit/v7"
	flatbuffers "github.com/google/flatbuffers/go"

	"portmaster/tests/integration/internal/fbs"
)

// Product is a generated product-create payload plus the values it carries.
type Product struct {
	Name      string
	Density   float64
	RiskClass fbs.RiskClass
	Bytes     []byte
}

// NewProduct builds a POST /products body.
func NewProduct() Product {
	name := gofakeit.Sentence(2)
	density := gofakeit.Float64Range(0.1, 5.0)
	risk := fbs.RiskClassClass3FlammableLiquids

	b := flatbuffers.NewBuilder(0)
	nameOff := b.CreateString(name)
	fbs.ProductCreateRequestStart(b)
	fbs.ProductCreateRequestAddName(b, nameOff)
	fbs.ProductCreateRequestAddDensity(b, density)
	fbs.ProductCreateRequestAddRiskClass(b, risk)
	b.Finish(fbs.ProductCreateRequestEnd(b))

	return Product{Name: name, Density: density, RiskClass: risk, Bytes: b.FinishedBytes()}
}

// Container is a generated container-create payload plus its values.
type Container struct {
	Code        string
	MaxCapacity float64
	Bytes       []byte
}

// NewContainer builds a POST /containers body with a unique code.
func NewContainer() Container {
	code := fmt.Sprintf("CT-%s", gofakeit.LetterN(8))
	capacity := 1000.0

	b := flatbuffers.NewBuilder(0)
	codeOff := b.CreateString(code)
	fbs.ContainerCreateRequestStart(b)
	fbs.ContainerCreateRequestAddCode(b, codeOff)
	fbs.ContainerCreateRequestAddMaxCapacity(b, capacity)
	b.Finish(fbs.ContainerCreateRequestEnd(b))

	return Container{Code: code, MaxCapacity: capacity, Bytes: b.FinishedBytes()}
}

// Role is a generated role-create payload plus its values.
type Role struct {
	Name        string
	Permissions []string
	Bytes       []byte
}

// NewRole builds a POST /roles body carrying the given permission slugs.
func NewRole(permissions ...string) Role {
	name := fmt.Sprintf("%s-%s", gofakeit.JobTitle(), gofakeit.LetterN(4))

	b := flatbuffers.NewBuilder(0)
	nameOff := b.CreateString(name)
	permOffsets := make([]flatbuffers.UOffsetT, len(permissions))
	for i, p := range permissions {
		permOffsets[i] = b.CreateString(p)
	}
	fbs.RoleCreateRequestStartPermissionsVector(b, len(permOffsets))
	for i := len(permOffsets) - 1; i >= 0; i-- {
		b.PrependUOffsetT(permOffsets[i])
	}
	permsVec := b.EndVector(len(permOffsets))

	fbs.RoleCreateRequestStart(b)
	fbs.RoleCreateRequestAddName(b, nameOff)
	fbs.RoleCreateRequestAddPermissions(b, permsVec)
	b.Finish(fbs.RoleCreateRequestEnd(b))

	return Role{Name: name, Permissions: permissions, Bytes: b.FinishedBytes()}
}

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

// LoadItem builds a POST /manifests/load-item body.
func LoadItem(containerID, productID string, quantity float64) []byte {
	b := flatbuffers.NewBuilder(0)
	cOff := b.CreateString(containerID)
	pOff := b.CreateString(productID)
	fbs.LoadItemRequestStart(b)
	fbs.LoadItemRequestAddContainerId(b, cOff)
	fbs.LoadItemRequestAddProductId(b, pOff)
	fbs.LoadItemRequestAddQuantity(b, quantity)
	b.Finish(fbs.LoadItemRequestEnd(b))
	return b.FinishedBytes()
}

// UnloadItem builds a POST /manifests/unload-item body.
func UnloadItem(containerID, productID string, quantity float64) []byte {
	b := flatbuffers.NewBuilder(0)
	cOff := b.CreateString(containerID)
	pOff := b.CreateString(productID)
	fbs.UnloadItemRequestStart(b)
	fbs.UnloadItemRequestAddContainerId(b, cOff)
	fbs.UnloadItemRequestAddProductId(b, pOff)
	fbs.UnloadItemRequestAddQuantity(b, quantity)
	b.Finish(fbs.UnloadItemRequestEnd(b))
	return b.FinishedBytes()
}

// AccountUpdate builds a PUT /account body.
func AccountUpdate(name, email string) []byte {
	b := flatbuffers.NewBuilder(0)
	nameOff := b.CreateString(name)
	emailOff := b.CreateString(email)
	fbs.AccountUpdateRequestStart(b)
	fbs.AccountUpdateRequestAddName(b, nameOff)
	fbs.AccountUpdateRequestAddEmail(b, emailOff)
	b.Finish(fbs.AccountUpdateRequestEnd(b))
	return b.FinishedBytes()
}

// PasswordChange builds a PUT /account/password body.
func PasswordChange(current, next string) []byte {
	b := flatbuffers.NewBuilder(0)
	curOff := b.CreateString(current)
	newOff := b.CreateString(next)
	fbs.AccountPasswordChangeRequestStart(b)
	fbs.AccountPasswordChangeRequestAddCurrentPassword(b, curOff)
	fbs.AccountPasswordChangeRequestAddNewPassword(b, newOff)
	b.Finish(fbs.AccountPasswordChangeRequestEnd(b))
	return b.FinishedBytes()
}

// ProductUpdate builds a PUT /products/{id} body.
func ProductUpdate() (name string, body []byte) {
	name = gofakeit.Sentence(2)
	b := flatbuffers.NewBuilder(0)
	nameOff := b.CreateString(name)
	fbs.ProductUpdateRequestStart(b)
	fbs.ProductUpdateRequestAddName(b, nameOff)
	fbs.ProductUpdateRequestAddDensity(b, gofakeit.Float64Range(0.1, 5.0))
	fbs.ProductUpdateRequestAddRiskClass(b, fbs.RiskClassClass2Gases)
	b.Finish(fbs.ProductUpdateRequestEnd(b))
	return name, b.FinishedBytes()
}

// ContainerUpdate builds a PUT /containers/{id} body.
func ContainerUpdate(maxCapacity float64) []byte {
	b := flatbuffers.NewBuilder(0)
	fbs.ContainerUpdateRequestStart(b)
	fbs.ContainerUpdateRequestAddMaxCapacity(b, maxCapacity)
	b.Finish(fbs.ContainerUpdateRequestEnd(b))
	return b.FinishedBytes()
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

// PasswordReset builds a PUT /users/{id}/password body.
func PasswordReset(newPassword string) []byte {
	b := flatbuffers.NewBuilder(0)
	pwOff := b.CreateString(newPassword)
	fbs.UserAdminPasswordResetRequestStart(b)
	fbs.UserAdminPasswordResetRequestAddNewPassword(b, pwOff)
	b.Finish(fbs.UserAdminPasswordResetRequestEnd(b))
	return b.FinishedBytes()
}

// RolePermissions builds a PUT /roles/{id}/permissions body.
func RolePermissions(permissions ...string) []byte {
	b := flatbuffers.NewBuilder(0)
	permOffsets := make([]flatbuffers.UOffsetT, len(permissions))
	for i, p := range permissions {
		permOffsets[i] = b.CreateString(p)
	}
	fbs.RolePermissionsUpdateRequestStartPermissionsVector(b, len(permOffsets))
	for i := len(permOffsets) - 1; i >= 0; i-- {
		b.PrependUOffsetT(permOffsets[i])
	}
	vec := b.EndVector(len(permOffsets))
	fbs.RolePermissionsUpdateRequestStart(b)
	fbs.RolePermissionsUpdateRequestAddPermissions(b, vec)
	b.Finish(fbs.RolePermissionsUpdateRequestEnd(b))
	return b.FinishedBytes()
}

// --- Invalid payloads -------------------------------------------------------
//
// Negative cases need bodies that are well-formed FlatBuffers but wrong by the
// domain's rules, so the failure comes from the table module rather than from
// the wire. Each of these is rejected by a specific documented rule.

// InvalidProduct builds a POST /products body with a blank name.
func InvalidProduct() []byte {
	b := flatbuffers.NewBuilder(0)
	nameOff := b.CreateString("")
	fbs.ProductCreateRequestStart(b)
	fbs.ProductCreateRequestAddName(b, nameOff)
	fbs.ProductCreateRequestAddDensity(b, 1.0)
	fbs.ProductCreateRequestAddRiskClass(b, fbs.RiskClassClass3FlammableLiquids)
	b.Finish(fbs.ProductCreateRequestEnd(b))
	return b.FinishedBytes()
}

// ContainerWithCode builds a POST /containers body reusing a known code, to
// exercise the uniqueness rule.
func ContainerWithCode(code string, capacity float64) []byte {
	b := flatbuffers.NewBuilder(0)
	codeOff := b.CreateString(code)
	fbs.ContainerCreateRequestStart(b)
	fbs.ContainerCreateRequestAddCode(b, codeOff)
	fbs.ContainerCreateRequestAddMaxCapacity(b, capacity)
	b.Finish(fbs.ContainerCreateRequestEnd(b))
	return b.FinishedBytes()
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

// Login builds a POST /auth/login body.
func Login(email, password string) []byte {
	b := flatbuffers.NewBuilder(0)
	emailOff := b.CreateString(email)
	passOff := b.CreateString(password)
	fbs.LoginRequestStart(b)
	fbs.LoginRequestAddEmail(b, emailOff)
	fbs.LoginRequestAddPassword(b, passOff)
	b.Finish(fbs.LoginRequestEnd(b))
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
