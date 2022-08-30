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

namespace ZfrOAuth2Test\Server\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ZfrOAuth2\Server\Container\PasswordGrantFactory;
use ZfrOAuth2\Server\Grant\PasswordGrant;
use ZfrOAuth2\Server\Options\ServerOptions;
use ZfrOAuth2\Server\Service\AccessTokenService;
use ZfrOAuth2\Server\Service\RefreshTokenService;

/**
 * @licence MIT
 * @covers  \ZfrOAuth2\Server\Container\PasswordGrantFactory
 */
class PasswordGrantFactoryTest extends TestCase
{
    public function testCanCreateFromFactory(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $callable  = function () {
        };
        $options   = ServerOptions::fromArray(['owner_callable' => $callable]);

        $container
            ->expects($this->exactly(3))
            ->method('get')
            ->withConsecutive([ServerOptions::class], [AccessTokenService::class], [RefreshTokenService::class])
            ->will(
                $this->onConsecutiveCalls(
                    $options,
                    $this->createMock(AccessTokenService::class),
                    $this->createMock(RefreshTokenService::class)
                )
            );

        $factory = new PasswordGrantFactory();
        $service = $factory($container);

        $this->assertInstanceOf(PasswordGrant::class, $service);
    }

    public function testCanCreateFromFactoryOwnerCallableOptionsIsString(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $callable  = function () {
        };
        $options   = ServerOptions::fromArray(['owner_callable' => 'service_name']);

        $container
            ->expects($this->exactly(4))
            ->method('get')
            ->withConsecutive(
                [ServerOptions::class],
                ['service_name'],
                [AccessTokenService::class],
                [RefreshTokenService::class]
            )
            ->will(
                $this->onConsecutiveCalls(
                    $options,
                    $callable,
                    $this->createMock(AccessTokenService::class),
                    $this->createMock(RefreshTokenService::class)
                )
            );

        $factory = new PasswordGrantFactory();
        $service = $factory($container);

        $this->assertInstanceOf(PasswordGrant::class, $service);
    }
}