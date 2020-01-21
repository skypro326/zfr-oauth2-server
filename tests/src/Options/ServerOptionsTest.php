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

namespace ZfrOAuth2Test\Server\Options;

use PHPUnit\Framework\TestCase;
use ZfrOAuth2\Server\Grant\ClientCredentialsGrant;
use ZfrOAuth2\Server\Options\ServerOptions;

/**
 * @author  Michaël Gallego <mic.gallego@gmail.com>
 * @licence MIT
 *
 * @covers  \ZfrOAuth2\Server\Options\ServerOptions
 */
class ServerOptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $options = ServerOptions::fromArray();

        $this->assertEquals(120, $options->getAuthorizationCodeTtl());
        $this->assertEquals(3600, $options->getAccessTokenTtl());
        $this->assertEquals(86400, $options->getRefreshTokenTtl());
        $this->assertNull($options->getOwnerCallable());
        $this->assertEmpty($options->getGrants());
        $this->assertFalse($options->getRotateRefreshTokens());
        $this->assertTrue($options->getRevokeRotatedRefreshTokens());
        $this->assertEquals('owner', $options->getOwnerRequestAttribute());
        $this->assertEquals('oauth_token', $options->getTokenRequestAttribute());
    }

    public function testGetters(): void
    {
        $callable = function () {
        };

        $options = ServerOptions::fromArray([
            'authorization_code_ttl' => 300,
            'access_token_ttl' => 3000,
            'refresh_token_ttl' => 30000,
            'rotate_refresh_tokens' => true,
            'revoke_rotated_refresh_tokens' => false,
            'owner_callable' => $callable,
            'grants' => [ClientCredentialsGrant::class],
            'owner_request_attribute' => 'something',
            'token_request_attribute' => 'else',
        ]);

        $this->assertEquals(300, $options->getAuthorizationCodeTtl());
        $this->assertEquals(3000, $options->getAccessTokenTtl());
        $this->assertEquals(30000, $options->getRefreshTokenTtl());
        $this->assertEquals(true, $options->getRotateRefreshTokens());
        $this->assertEquals(false, $options->getRevokeRotatedRefreshTokens());
        $this->assertSame($callable, $options->getOwnerCallable());
        $this->assertEquals([ClientCredentialsGrant::class], $options->getGrants());
        $this->assertEquals('something', $options->getOwnerRequestAttribute());
        $this->assertEquals('else', $options->getTokenRequestAttribute());
    }
}
