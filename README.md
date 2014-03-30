# ZfrOAuth2Server

[![Build Status](https://travis-ci.org/zf-fr/zfr-oauth2-server.png)](https://travis-ci.org/zf-fr/zfr-oauth2-server)
[![Latest Stable Version](https://poser.pugx.org/zfr/zfr-oauth2-server/v/stable.png)](https://packagist.org/packages/zfr/zfr-oauth2-server)
[![Coverage Status](https://coveralls.io/repos/zf-fr/zfr-oauth2-server/badge.png)](https://coveralls.io/r/zf-fr/zfr-oauth2-server)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/zf-fr/zfr-oauth2-server/badges/quality-score.png?s=be36235c9898cfc55044f58d9bba789d2d4d102e)](https://scrutinizer-ci.com/g/zf-fr/zfr-oauth2-server/)
[![Total Downloads](https://poser.pugx.org/zfr/zfr-oauth2-server/downloads.png)](https://packagist.org/packages/zfr/zfr-oauth2-server)

ZfrOAuth2Server is a PHP library that implement the OAuth 2 specification. It's main goal is to be a clean, PHP 5.4+
library that aims to be used with Doctrine 2 only.

Currently, ZfrOAuth2Server does not implement the whole specification (implicit grant is missing), so you are
encouraged to have a look at the doc if ZfrOAuth2Server can be used in your application.

Here are other OAuth2 library you can use:

- [OAuth2 Server from PHP-League](https://github.com/php-loep/oauth2-server)
- [OAuth2 Server from Brent Shaffer](https://github.com/bshaffer/oauth2-server-php)

## Requirements

- PHP 5.4 or higher
- Doctrine 2

## To-do

- Write documentation
- Security audit
- Review of the whole spec
- Testing the authorization server more extensively
- Add implicit grant

## Versioning note

Please note that until I reach 1.0, I **WILL NOT** follow semantic version. This means that BC can occur between
0.1.x and 0.2.x releases. If you are using this in production, please set your dependency using 0.1.*, for instance.

## Installation

Installation is only officially supported using Composer:

```sh
php composer.phar require zfr/zfr-oauth2-server:0.1.*
```

## Framework integration

Because of its strict dependency injection architecture, ZfrOAuth2Server is hardly usable alone, as it requires
quite a lot of configuration. However, I've made a Zend Framework 2 module that abstract the whole configuration,
and make it very easy to use:

* [Zend Framework 2 module](https://github.com/zf-fr/zfr-oauth2-server-module)

If anyone want to help with a Symfony 2 bundle, I'd be glad to help.


## Documentation

ZfrOAuth2Server is based on the [RFC 6749](http://tools.ietf.org/html/rfc6749) documentation.

### Why using OAuth2?

OAuth2 is an authentication/authorization system that allows that can be used to:

* Implement a stateless authentication mechanism (useful for API)
* Allow third-party to connect to your application securely
* Securing your application through the use of scopes

OAuth2 is a dense, extensible specification that can be used for a wide number of use-cases. As of today,
ZfrOAuth2Server implements three of the four official grants: AuthorizationGrant, ClientCredentialsGrant, PasswordGrant.

### How OAuth2 works?

This documentation does not aim to explain in details how OAuth2 work. Here is [a nice resource](http://aaronparecki.com/articles/2012/07/29/1/oauth2-simplified) you can read. However, here is the basic idea of how OAuth2 works:

1. A resource owner (your JavaScript API, your mobile application...) asks for a so-called "access token" to an
 authorization server. There are several strategies that depends on the use-case. Those strategies are called
 "grants". For instance, the "password grant" assumes that the resource owner sends its username/password. In all
 cases, your authorization server responds with an access token (and an optional refresh token).
2. The client sends this access token to each request that is made to your API. It is used by a "resource server"
to map this access token to a user in your system.

Choosing the grant type depends on your application. Here are a few hints about which one to choose:

* If you are the only consumer of your API (for instance, your JavaScript application make calls to your API), you
should use the "password grant". Because you trust your application, it is not a problem to send username/password.
* If you want a third-party code to connect to your API, and that you are sure that this third-party can keep secrets
(this means the client is not a JavaScript API, or a mobile application): you can use the client credentials grant.
* If you want third-party code to connect to your API, and that those third-party applications cannot keep secret
(think about an unofficial Twitter client that connect to your Twitter account, for instance), you should use the
authorization grant.

### Using the authorization server

The authorization server goal is to accept a request, and generate token. An authorization server can deny a
request (for instance, if parameters are missing, or if username/password are incorrect).

To use an authorization server, you must first decide which grant you want to support. Some applications should
only support one type of grant, others may support all of the available grant. This is completely up to you, and
you should have a solid understanding of all those grants first. For instance, here is how you would create an
authorization server that support the authorization only:

```php
$authTokenService    = new TokenService($objectManager, $authTokenRepository, $scopeRepository);
$accessTokenService  = new TokenService($objectManager, $accessTokenRepository, $scopeRepository);
$refreshTokenService = new TokenService($objectManager, $refreshTokenRepository, $scopeRepository);

$authorizationGrant  = new AuthorizationGrant($authTokenService, $accessTokenService, $refreshTokenService);
$authorizationServer = new AuthorizationServer([$authorizationGrant]);

// Response contains the various parameters you can return
$response = $authorizationServer->handleRequest($request);
```

The request must be a valid `Zend\Http\Request`, and the authorization server returns a `Zend\Http\Response` object
that is compliant with the OAuth2 specification.

#### Passing a user

Most of the time, you want to associate an access token to a user. This is the only way to map a token to a user
of your system. To do this, you can pass an optional second parameter to the `handleRequest`. This class must
implements the `ZfrOAuth2\Server\Entity\TokenOwnerInterface` interface:

```php
$user = new User(); // must implement TokenOwnerInterface

// ...

$response = $authorizationServer->handleRequest($request, $user);
```

### Using the resource server

You can use the resource server to retrieve the access token (by automatically extracting the data from the HTTP
headers). You can also use the resource server to validate the access token against scopes:

```php
$accessTokenService = new TokenService($objectManager, $accessTokenRepository, $scopeRepository);
$resourceServer     = new ResourceServer($accessTokenService);

if (!$resourceServer->isRequestValid($request, ['write']) {
    // there is either no access token, or the access token is expired, or the access token does not have
    // the `write` scope
}
```

You can also manually retrieve the access token:

```php
$accessToken = $resourceServer->getAccessToken($request);
```

### Doctrine

ZfrOAuth2Server is built to be used with Doctrine (either ORM or ODM). Out of the box, it provides ORM mapping for
Doctrine (in the `config/doctrine` folder).
