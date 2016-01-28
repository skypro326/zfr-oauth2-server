<?php
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

namespace ZfrOAuth2Test\Server\Model;

use DateInterval;
use DateTime;
use ZfrOAuth2\Server\Model\Client;
use ZfrOAuth2\Server\Model\RefreshToken;
use ZfrOAuth2\Server\Model\Scope;
use ZfrOAuth2\Server\Model\TokenOwnerInterface;

/**
 * @author  Michaël Gallego <mic.gallego@gmail.com>
 * @licence MIT
 * @covers \ZfrOAuth2\Server\Model\AbstractToken
 * @covers \ZfrOAuth2\Server\Model\RefreshToken
 */
class RefreshTokenTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider providerGenerateNewRefreshToken
     */
    public function testGenerateNewAccessToken($ttl, $owner, $client, $scopes)
    {
        /** @var RefreshToken $refreshToken */
        $refreshToken = RefreshToken::generateNewRefreshToken($ttl, $owner, $client, $scopes);

        $expiresAt = (new \DateTimeImmutable())->modify("+$ttl seconds");

        $this->assertNotEmpty($refreshToken->getToken());
        $this->assertEquals(40, strlen($refreshToken->getToken()));
        $this->assertCount(count($scopes), $refreshToken->getScopes());
        $this->assertSame($client, $refreshToken->getClient());
        $this->assertEquals($expiresAt, $refreshToken->getExpiresAt());
        $this->assertSame($owner, $refreshToken->getOwner());
    }

    public function providerGenerateNewRefreshToken()
    {
        return [
            [
                3600,
                $this->getMock(TokenOwnerInterface::class),
                $this->getMock(Client::class, [], [], '', false),
                ['scope1', 'scope2']
            ],
            [
                3600,
                $this->getMock(TokenOwnerInterface::class),
                $this->getMock(Client::class, [], [], '', false),
                'scope1'
            ],
            [3600, null, null, null]
        ];
    }

    /**
     * @dataProvider providerReconstitute
     */
    public function testReconstitute($data)
    {
        /** @var RefreshToken $refreshToken */
        $refreshToken = RefreshToken::reconstitute($data);


        $this->assertEquals($data['token'], $refreshToken->getToken());

        if (isset($data['owner'])) {
            $this->assertSame($data['owner'], $refreshToken->getOwner());
        } else {
            $this->assertNull($refreshToken->getOwner());
        }

        if (isset($data['client'])) {
            $this->assertSame($data['client'], $refreshToken->getClient());
        } else {
            $this->assertNull($refreshToken->getClient());
        }

        if (isset($data['expiresAt'])) {
            $this->assertInstanceOf(\DateTimeImmutable::class, $refreshToken->getExpiresAt());
            /** @var \DateTimeImmutable $expiresAt */
            $expiresAt = $data['expiresAt'];
            $this->assertSame($expiresAt->getTimeStamp(), $refreshToken->getExpiresAt()->getTimestamp());
        } else {
            $this->assertNull($refreshToken->getExpiresAt());
        }

        if (isset($data['scopes'])) {
            if (is_string($data['scopes'])) {
                $data['scopes'] = explode(" ", $data['scopes']);
            }
            $this->assertCount(count($data['scopes']), $refreshToken->getScopes());
        } else {
            $this->assertTrue(is_array($refreshToken->getScopes()));
            $this->assertEmpty($refreshToken->getScopes());
        }
    }


    public function providerReconstitute()
    {
        return [
            [
                [
                    'token'     => 'token',
                    'owner'     => $this->getMock(TokenOwnerInterface::class),
                    'client'    => $this->getMock(Client::class, [], [], '', false),
                    'expiresAt' => new \DateTimeImmutable(),
                    'scopes'    => ['scope1', 'scope2'],
                ]
            ],
            [ // test set - null values
                [
                    'token'     => 'token',
                    'owner'     => null,
                    'client'    => null,
                    'expiresAt' => null,
                    'scopes'    => null,
                ]
            ],
            [ // test set - scopes from string
                [
                  'token'  => 'token',
                  'scopes' => 'read write',
                ]
            ],
            [ // test set - scope from instance
                [
                    'token'  => 'token',
                    'scopes' => Scope::createNewScope(1, 'read'),
                ]
            ],
            [ // test set - scope from mixed array
              [
                  'token'  => 'token',
                  'scopes' => [Scope::createNewScope(1, 'read'), 'write'],
              ]
            ],
        ];
    }

    public function testCalculateExpiresIn()
    {
        $refreshToken = RefreshToken::generateNewRefreshToken(60);

        $this->assertFalse($refreshToken->isExpired());
        $this->assertEquals(60, $refreshToken->getExpiresIn());
    }

    public function testCanCheckIfATokenIsExpired()
    {
        $refreshToken = RefreshToken::generateNewRefreshToken(-60);

        $this->assertTrue($refreshToken->isExpired());
    }

    public function testSupportLongLiveToken()
    {
        $refreshToken = RefreshToken::generateNewRefreshToken(60);
        $this->assertFalse($refreshToken->isExpired());
    }

    public function testIsValid()
    {
        $accessToken = RefreshToken::generateNewRefreshToken(60, null, null, 'read write');
        $this->assertTrue($accessToken->isValid('read'));

        $accessToken = RefreshToken::generateNewRefreshToken(-60, null, null, 'read write');
        $this->assertFalse($accessToken->isValid('read'));

        $accessToken = RefreshToken::generateNewRefreshToken(60, null, null, 'read write');
        $this->assertFalse($accessToken->isValid('delete'));
    }
}
