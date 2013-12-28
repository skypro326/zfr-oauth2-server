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

namespace ZfrOAuth2\Server;

use Zend\Http\Request as HttpRequest;
use Zend\Http\Response as HttpResponse;
use ZfrOAuth2\Server\Entity\Client;
use ZfrOAuth2\Server\Exception\OAuth2Exception;
use ZfrOAuth2\Server\Grant\AuthorizationServiceAwareInterface;
use ZfrOAuth2\Server\Grant\GrantInterface;
use ZfrOAuth2\Server\Service\ClientService;

/**
 * The authorization server main role is to create access tokens or refresh tokens
 *
 * @author  Michaël Gallego <mic.gallego@gmail.com>
 * @licence MIT
 */
class AuthorizationServer
{
    /**
     * @var ClientService
     */
    protected $clientService;

    /**
     * A list of grant interfaces
     *
     * @var GrantInterface[]
     */
    protected $grants = [];

    /**
     * A list of grant interfaces that can answer to an authorization request
     *
     * @var GrantInterface[]
     */
    protected $responseTypes = [];

    /**
     * @param ClientService    $clientService
     * @param GrantInterface[] $grants
     */
    public function __construct(ClientService $clientService, array $grants)
    {
        $this->clientService = $clientService;

        foreach ($grants as $grant) {
            if ($grant instanceof AuthorizationServiceAwareInterface) {
                $grant->setAuthorizationServer($this);
            }

            $this->grants[$grant::GRANT_TYPE] = $grant;

            if (empty($grant::GRANT_RESPONSE_TYPE)) {
                $this->responseTypes[$grant::GRANT_RESPONSE_TYPE] = $grant;
            }
        }
    }

    /**
     * Check if the authorization server supports this grant
     *
     * @param  string $grantType
     * @return bool
     */
    public function hasGrant($grantType)
    {
        return isset($this->grants[$grantType]);
    }

    /**
     * Get the grant by its name
     *
     * @param  string $grantType
     * @return GrantInterface
     * @throws OAuth2Exception If grant type is not registered by this authorization server
     */
    public function getGrant($grantType)
    {
        if ($this->hasGrant($grantType)) {
            return $this->grants[$grantType];
        }

        // If we reach here... then no grant was found. Not good!
        throw OAuth2Exception::unsupportedGrantType(sprintf(
            'Grant type "%s" is not supported by this server',
            $grantType
        ));
    }

    /**
     * Check if the authorization server supports this response type
     *
     * @param  string $responseType
     * @return bool
     */
    public function hasResponseType($responseType)
    {
        return isset($this->responseTypes[$responseType]);
    }

    /**
     * Get the response type by its name
     *
     * @param  string $responseType
     * @return GrantInterface
     * @throws Exception\OAuth2Exception
     */
    public function getResponseType($responseType)
    {
        if ($this->hasResponseType($responseType)) {
            return $this->responseTypes[$responseType];
        }

        // If we reach here... then no grant was found. Not good!
        throw OAuth2Exception::unsupportedGrantType(sprintf(
            'Grant type "%s" is not supported by this server',
            $responseType
        ));
    }

    /**
     * Handle the request
     *
     * @param  HttpRequest $request
     * @return HttpResponse
     * @throws OAuth2Exception If no grant type could be found
     */
    public function handleRequest(HttpRequest $request)
    {
        try {
            // If it's a GET, it's an authorization endpoint, if it's a POST, it's a token endpoint!
            if ($request->isGet()) {
                $response = $this->handleAuthorizationRequest($request);
            } elseif ($request->isPost()) {
                $response = $this->handleTokenRequest($request);
            } else {
                throw OAuth2Exception::invalidRequest(sprintf(
                    'Only GET and POST verbs are supported by the authorization server, "%s" given',
                    $request->getMethod()
                ));
            }
        } catch (OAuth2Exception $exception) {
            return $this->createResponseFromOAuthException($exception);
        }

        // According to the spec, we must set those headers (http://tools.ietf.org/html/rfc6749#section-5.1)
        $response->getHeaders()->addHeaderLine('Cache-Control', 'no-store')
                               ->addHeaderLine('Pragma', 'no-cache');

        return $response;
    }

    /**
     * @param  HttpRequest $request
     * @return HttpResponse
     * @throws OAuth2Exception If no "response_type" could be found in the GET parameters
     */
    protected function handleAuthorizationRequest(HttpRequest $request)
    {
        $responseType = $request->getQuery('response_type');

        if (null === $responseType) {
            throw OAuth2Exception::invalidRequest('No grant response type was found in the request');
        }

        $responseType = $this->getResponseType($responseType);
        $client       = $this->getClient($request, $responseType->allowPublicClients());

        return $responseType->createAuthorizationResponse($request, $client);
    }

    /**
     * @param  HttpRequest $request
     * @return HttpResponse
     * @throws OAuth2Exception If no "grant_type" could be found in the POST parameters
     */
    protected function handleTokenRequest(HttpRequest $request)
    {
        $grant = $request->getPost('grant_type');

        if (null === $grant) {
            throw OAuth2Exception::invalidRequest('No grant type was found in the request');
        }

        $grant  = $this->getGrant($grant);
        $client = $this->getClient($request, $grant->allowPublicClients());

        return $grant->createTokenResponse($request, $client);
    }

    /**
     * Get the client
     *
     * According to the spec (http://tools.ietf.org/html/rfc6749#section-2.3), for public clients we do
     * not need to authenticate them
     *
     * @param  HttpRequest $request
     * @param  bool        $allowPublicClients
     * @return Client
     * @throws Exception\OAuth2Exception
     */
    protected function getClient(HttpRequest $request, $allowPublicClients)
    {
        // We first try to get the Authorization header, as this is the recommended way according to the spec
        if ($header = $request->getHeader('Authorization')) {
            // The value is "Basic xxx", we are interested in the last part
            $parts = explode(' ', $header->getFieldValue());
            $value = base64_decode(end($parts));

            list($id, $secret) = explode(':', $value);
        } else {
            $id     = $request->getPost('client_id');
            $secret = $request->getPost('client_secret');
        }

        // If the grant type we are issuing does not allow public clients, and that the secret is
        // missing, then we have an error...
        if (!$allowPublicClients && !$secret) {
            throw OAuth2Exception::invalidClient('Client secret is missing');
        }

        $client = $this->clientService->getClient($id);

        // We delegate all the checks to the client service
        if (null === $client || $this->clientService->isClientValid($client, $secret, $allowPublicClients)) {
            throw OAuth2Exception::invalidClient('Client cannot authenticated');
        }

        return $client;
    }

    /**
     * Create a response from the exception, using the format of the spec
     *
     * @link   http://tools.ietf.org/html/rfc6749#section-5.2
     * @param  OAuth2Exception $exception
     * @return HttpResponse
     */
    protected function createResponseFromOAuthException(OAuth2Exception $exception)
    {
        $response = new HttpResponse();
        $response->setStatusCode(400);

        $body = ['error' => $exception->getCode(), 'error_description' => $exception->getMessage()];
        $response->setContent(json_encode($body));

        return $response;
    }
}
