<?php

declare(strict_types=1);

/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ZfrOAuth2\Server\Options;

/**
 * Options class
 *
 * @licence MIT
 */
final class ServerOptions
{
    /**
     * Authorization code TTL
     *
     * @var int
     */
    private $authorizationCodeTtl = 120;

    /**
     * Access token TTL
     *
     * @var int
     */
    private $accessTokenTtl = 3600;

    /**
     * Refresh token TTL
     *
     * @var int
     */
    private $refreshTokenTtl = 86400;

    /**
     * Rotate refresh tokens (for RefreshTokenGrant)
     *
     * @var bool
     */
    private $rotateRefreshTokens = false;

    /**
     * Revoke rotated refresh tokens (for RefreshTokenGrant)
     *
     * @var bool
     */
    private $revokeRotatedRefreshTokens = true;

    /**
     * Set the owner callable
     *
     * @var callable|string
     */
    private $ownerCallable;

    /**
     * Grants that the authorization server must support
     *
     * @var array
     */
    private $grants = [];

    /**
     * Attribute that the AuthorizationRequestMiddleware expects the ZfrOAuth2\Server\Model\TokenOwnerInterface
     * to be present on
     *
     * @var string
     */
    private $ownerRequestAttribute;

    /**
     * Attribute that the ResourceServerMiddleware uses to access the token
     *
     * @var string
     */
    private $tokenRequestAttribute;

    /**
     * @param callable|string $ownerCallable either a callable or the name of a container service
     */
    private function __construct(
        int $authorizationCodeTtl,
        int $accessTokenTtl,
        int $refreshTokenTtl,
        bool $rotateRefreshTokens,
        bool $revokeRotatedRefreshTokens,
        $ownerCallable,
        array $grants,
        string $ownerRequestAttribute,
        string $tokenRequestAttribute
    ) {
        $this->authorizationCodeTtl       = $authorizationCodeTtl;
        $this->accessTokenTtl             = $accessTokenTtl;
        $this->refreshTokenTtl            = $refreshTokenTtl;
        $this->rotateRefreshTokens        = $rotateRefreshTokens;
        $this->revokeRotatedRefreshTokens = $revokeRotatedRefreshTokens;
        $this->ownerCallable              = $ownerCallable;
        $this->grants                     = $grants;
        $this->ownerRequestAttribute      = $ownerRequestAttribute;
        $this->tokenRequestAttribute      = $tokenRequestAttribute;
    }

    /**
     * Set one or more configuration properties
     */
    public static function fromArray(array $options = []): self
    {
        return new self(
            $options['authorization_code_ttl'] ?? 120,
            $options['access_token_ttl'] ?? 3600,
            $options['refresh_token_ttl'] ?? 86400,
            $options['rotate_refresh_tokens'] ?? false,
            $options['revoke_rotated_refresh_tokens'] ?? true,
            $options['owner_callable'] ?? null,
            $options['grants'] ?? [],
            $options['owner_request_attribute'] ?? 'owner',
            $options['token_request_attribute'] ?? 'oauth_token'
        );
    }

    /**
     * Get the authorization code TTL
     */
    public function getAuthorizationCodeTtl(): int
    {
        return $this->authorizationCodeTtl;
    }

    /**
     * Get the access token TTL
     */
    public function getAccessTokenTtl(): int
    {
        return $this->accessTokenTtl;
    }

    /**
     * Get the refresh token TTL
     */
    public function getRefreshTokenTtl(): int
    {
        return $this->refreshTokenTtl;
    }

    /**
     * Get the rotate refresh token option while refreshing an access token
     */
    public function getRotateRefreshTokens(): bool
    {
        return $this->rotateRefreshTokens;
    }

    /**
     * Get the revoke rotated refresh token option while refreshing an access token
     */
    public function getRevokeRotatedRefreshTokens(): bool
    {
        return $this->revokeRotatedRefreshTokens;
    }

    /**
     * Get the callable used to validate a user
     *
     * @return callable|string
     */
    public function getOwnerCallable()
    {
        return $this->ownerCallable;
    }

    /**
     * Get the grants the authorization server must support
     */
    public function getGrants(): array
    {
        return $this->grants;
    }

    /**
     * Gets attribute that the AuthorizationRequestMiddleware expects the ZfrOAuth2\Server\Model\TokenOwnerInterface
     * to be present on
     */
    public function getOwnerRequestAttribute(): string
    {
        return $this->ownerRequestAttribute;
    }

    /**
     * Attribute that the ResourceServerMiddleware uses to access the token
     */
    public function getTokenRequestAttribute(): string
    {
        return $this->tokenRequestAttribute;
    }
}
