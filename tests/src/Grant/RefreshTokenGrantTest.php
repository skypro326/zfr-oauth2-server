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

namespace ZfrOAuth2Test\Server\Grant;

use DateInterval;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ZfrOAuth2\Server\Exception\OAuth2Exception;
use ZfrOAuth2\Server\Grant\RefreshTokenGrant;
use ZfrOAuth2\Server\Model\AccessToken;
use ZfrOAuth2\Server\Model\Client;
use ZfrOAuth2\Server\Model\RefreshToken;
use ZfrOAuth2\Server\Model\TokenOwnerInterface;
use ZfrOAuth2\Server\Options\ServerOptions;
use ZfrOAuth2\Server\Service\AccessTokenService;
use ZfrOAuth2\Server\Service\RefreshTokenService;

/**
 * @author  Michaël Gallego <mic.gallego@gmail.com>
 * @licence MIT
 * @covers  \ZfrOAuth2\Server\Grant\RefreshTokenGrant
 */
class RefreshTokenGrantTest extends TestCase
{
    use PHPMock;

    /**
     * @var AccessTokenService|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $accessTokenService;

    /**
     * @var RefreshTokenService|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $refreshTokenService;

    /**
     * @var RefreshTokenGrant
     */
    protected $grant;

    public function setUp(): void
    {
        $this->accessTokenService = $this->createMock(AccessTokenService::class);
        $this->refreshTokenService = $this->createMock(RefreshTokenService::class);
    }

    public function testAssertDoesNotImplementAuthorization(): void
    {
        $grant = new RefreshTokenGrant($this->accessTokenService, $this->refreshTokenService, ServerOptions::fromArray());

        $this->expectException(OAuth2Exception::class, null, 'invalid_request');
        $grant->createAuthorizationResponse(
            $this->createMock(ServerRequestInterface::class),
            Client::createNewClient('id', 'http://www.example.com')
        );
    }

    public function testAssertInvalidIfNoRefreshTokenIsFound(): void
    {
        $grant = new RefreshTokenGrant($this->accessTokenService, $this->refreshTokenService, ServerOptions::fromArray());

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())->method('getParsedBody')->willReturn([]);

        $this->expectException(OAuth2Exception::class, null, 'invalid_request');
        $grant->createTokenResponse($request, Client::createNewClient('id', 'http://www.example.com'));
    }

    public function testAssertInvalidIfRefreshTokenIsExpired(): void
    {
        $grant = new RefreshTokenGrant($this->accessTokenService, $this->refreshTokenService, ServerOptions::fromArray());

        $this->expectException(OAuth2Exception::class, null, 'invalid_grant');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())->method('getParsedBody')->willReturn(['refresh_token' => '123']);

        $refreshToken = $this->getExpiredRefreshToken();

        $this->refreshTokenService->expects($this->once())
            ->method('getToken')
            ->with('123')
            ->will($this->returnValue($refreshToken));

        $grant->createTokenResponse($request, Client::createNewClient('name', []));
    }

    public function testAssertExceptionIfAskedScopeIsSuperiorToRefreshToken(): void
    {
        $time = $this->getFunctionMock('ZfrOAuth2\Server\Model', 'time');
        $time->expects($this->any())->willReturn(10000);

        $grant = new RefreshTokenGrant($this->accessTokenService, $this->refreshTokenService, ServerOptions::fromArray());

        $this->expectException(OAuth2Exception::class, null, 'invalid_scope');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())->method('getParsedBody')->willReturn([
            'refresh_token' => '123',
            'scope' => 'read write',
        ]);

        $refreshToken = $this->getValidRefreshToken(null, ['read']);

        $this->refreshTokenService->expects($this->once())
            ->method('getToken')
            ->with('123')
            ->will($this->returnValue($refreshToken));

        $grant->createTokenResponse($request, Client::createNewClient('name', []));
    }

    public function grantOptions(): array
    {
        return [
            [true, false],
            [false, true],
            [true, true],
            [false, false],
        ];
    }

    /**
     * @dataProvider grantOptions
     */
    public function testCanCreateTokenResponse(bool $rotateRefreshToken, bool $revokeRotatedRefreshToken): void
    {
        $time = $this->getFunctionMock('ZfrOAuth2\Server\Model', 'time');
        $time->expects($this->any())->willReturn(10000);

        $grant = new RefreshTokenGrant($this->accessTokenService, $this->refreshTokenService, ServerOptions::fromArray([
            'rotate_refresh_tokens' => $rotateRefreshToken,
            'revoke_rotated_refresh_tokens' => $revokeRotatedRefreshToken,
        ]));

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())->method('getParsedBody')->willReturn([
            'refresh_token' => '123',
            'scope' => 'read',
        ]);

        $owner = $this->createMock(TokenOwnerInterface::class);
        $owner->expects($this->once())->method('getTokenOwnerId')->will($this->returnValue(1));

        $refreshToken = $this->getValidRefreshToken($owner, ['read']);
        $this->refreshTokenService->expects($this->once())
            ->method('getToken')
            ->with('123')
            ->will($this->returnValue($refreshToken));

        if ($rotateRefreshToken) {
            $this->refreshTokenService->expects($revokeRotatedRefreshToken ? $this->once() : $this->never())
                ->method('deleteToken')
                ->with($refreshToken);

            $refreshToken = $this->getValidRefreshToken();
            $this->refreshTokenService->expects($this->once())->method('createToken')->will($this->returnValue($refreshToken));
        }

        $accessToken = $this->getValidAccessToken($owner);
        $this->accessTokenService->expects($this->once())->method('createToken')->will($this->returnValue($accessToken));

        $response = $grant->createTokenResponse($request, Client::createNewClient('name', []));

        $body = json_decode((string) $response->getBody(), true);

        $this->assertEquals('azerty_access', $body['access_token']);
        $this->assertEquals('Bearer', $body['token_type']);
        $this->assertEquals(3600, $body['expires_in']);
        $this->assertEquals('read', $body['scope']);
        $this->assertEquals(1, $body['owner_id']);
        $this->assertEquals('azerty_refresh', $body['refresh_token']);
    }

    private function getExpiredRefreshToken(): RefreshToken
    {
        $validDate = (new \DateTimeImmutable('@10000'))->sub(new DateInterval('P1D'));
        $token = RefreshToken::reconstitute([
            'token' => 'azerty_refresh',
            'owner' => null,
            'client' => null,
            'scopes' => [],
            'expiresAt' => $validDate,
        ]);

        return $token;
    }

    private function getValidRefreshToken(TokenOwnerInterface $owner = null, array $scopes = null): RefreshToken
    {
        $validDate = (new \DateTimeImmutable('@10000'))->add(new DateInterval('P1D'));
        $token = RefreshToken::reconstitute([
            'token' => 'azerty_refresh',
            'owner' => $owner,
            'client' => null,
            'scopes' => $scopes ?? ['read'],
            'expiresAt' => $validDate,
        ]);

        return $token;
    }

    private function getValidAccessToken(TokenOwnerInterface $owner = null, array $scopes = null): AccessToken
    {
        $validDate = (new \DateTimeImmutable('@10000'))->add(new DateInterval('PT1H'));
        $token = AccessToken::reconstitute([
            'token' => 'azerty_access',
            'owner' => $owner,
            'client' => null,
            'scopes' => $scopes ?? ['read'],
            'expiresAt' => $validDate,
        ]);

        return $token;
    }

    public function testMethodGetType(): void
    {
        $grant = new RefreshTokenGrant($this->accessTokenService, $this->refreshTokenService, ServerOptions::fromArray());
        $this->assertSame('refresh_token', $grant->getType());
    }

    public function testMethodGetResponseType(): void
    {
        $grant = new RefreshTokenGrant($this->accessTokenService, $this->refreshTokenService, ServerOptions::fromArray());
        $this->assertSame('', $grant->getResponseType());
    }

    public function testMethodAllowPublicClients(): void
    {
        $grant = new RefreshTokenGrant($this->accessTokenService, $this->refreshTokenService, ServerOptions::fromArray());
        $this->assertTrue($grant->allowPublicClients());
    }
}
