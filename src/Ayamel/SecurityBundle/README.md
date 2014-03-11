# SecurityBundle #

This bundle adds custom security handling for Ayamel API.  There are several scenarios to account for.

There 2 types of "users":

* ApiUser - native system accounts - people sign up and use their account to create "ApiClients"
* ApiClient - nested document in ApiUser, an ApiUser can have multiple ApiClients.  These are registered 
  client systems with API Keys.

ApiClient end-users are not represented internally - they are only identifiable by string references.

## Authentication ##

There are 3 types of authentication mechanisms:

* Form Login - for the `/client` management interfaces
* API Key - for client systems using `/api`
* Policy Session - for client system end-users directly interacting with `/api`

## Authorization ##

Whether or not an action can be performed is determined by an AuthorizationPolicy.  When authenticated via
API Key, the AuthorizationPolicy contains no restrictions.

ApiClients can create more restrictive AuthPolicies by creating a PolicySession.  This session can then be handed
to end-users of the ApiClient.  This allows end-users of the ApiClient use the Ayamel API directly, without intermediaries.

### Form Login ###

Typical website form login with a server-side session, nothing special.  This only applies to the web interfaces at `/clients`, and
does not permit actual API usage.

### Api Key ###

Simply passing an api key in the query string to the API.  Eventually the key should be used for request signing, rather 
than passing it in the clear.  The api key is passed in the query param `_key`, or can (eventually) be sent in a custom 
authorization header.

### Policy Session ###

An ApiClient may use the `POST /api/v1/auth/sessions` API to create a PolicySession.  Creating a policy session generates
a one-time-use token, which can be handed to an end-user system.  Upon first use of a valid PolicySession token - the created
AuthorizationPolicy will be associated with a traditional server-side session.  Further requests to the API can be made by 
simply exchanging the SessionId, until the associated AuthorizationPolicy expires.

Example request flow:

* `POST /api/v1/auth/sessions?_key=123clientapikey456` - A client system creates a policy session, posting a policy object
  that describes any restrictions to apply to the session.  This returns:

  ```json
  {
    "token": "123456one-time-use-session-creation-token123456",
    "policy": //... created policy object
  }
  ```

  This token is stored server side, associated with the policy object that was created.
* Client system hands token `123456one-time-use-session-creation-token123456` to an end-user client system.
* End-user client (a person's web browser), makes a request to the API, passing `123456one-time-use-session-creation-token123456`
  in an authorization header, or as a query parameter. By doing this, a new server-side session is created, and the policy object 
  that was associated with the token is now stored in the session.
* All further requests simply retrieve the AuthorizationPolicy in the stored session, and the session remains valid until the
  expiration defined in the AuthorizationPolicy object.
* If a client ever makes a request with a new policy session token, the previous session is immediatley wiped, and the new session
  with the new policy is applied.
* ApiClients can make use of the `DELETE /api/v1/auth/sessions` API to invalidate sessions in use by their end-users
* The contents of the AuthorizationPolicy are retrievable via `GET /api/v1/auth/sessions`, but only by the 
  session holder, or ApiClient that initiated the session.

#### Example Authorization Policy ####

An example of a policy that would be created at `POST /api/v1/auth/sessions`:

```yaml
expires: 3600
clientUser: 'appUserId'             #required
actions:
  resource:
    create:
      fields:
        type: ['video','audio']     #can only create audio/video resources
    delete:
      fields:
        clientUser: ['appUserId']   #user can only delete resources they created
    view:
      fields:
        clientUser: []
    modify:
      fields:
        id: [...]                   #array of resource ids to restrict
        clientUser: [...]           #array of client user ids - maybe they can modify things from other users?
  relation:
    create: false
    delete: false
    modify: false
    view:
      fields:
        client: ['fml']             #can only view relations created by this client system
```
