<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use Gec\Users\Models\User;

class InternalApiCaller
{
    /**
     * Call an internal API as a given user.
     *
     * @param string $uri
     * @param mixed $user
     * @param array $payload
     * @param string $method
     * @return static
     */
    public static function call(string $uri, $user = null, array $payload = [], string $method = 'GET'): self
    {
        $instance = new static();
        $instance->response = $instance->makeCall($uri, $user, $payload, $method);
        return $instance;
    }

    /**
     * @var Response
     */
    private $response;

    /**
     * Make the actual API call.
     *
     * @param string $uri
     * @param mixed $user
     * @param array $payload
     * @param string $method
     * @return Response
     */
    private function makeCall(string $uri, $user = null, array $payload = [], string $method = 'GET'): Response
    {

        // Find user if an ID is passed
        if (is_int($user)) {
            $user = User::findOrFail($user);
        }

        // Authenticate the user
        if ($user) {
            Auth::setUser($user);
        }

        // Create the request
        $request = Request::create($uri, strtoupper($method), $payload);

        // Dispatch the request
        $kernel = App::make(\Illuminate\Contracts\Http\Kernel::class);
        return $kernel->handle($request);
    }

    /**
     * Get the JSON-decoded content from the response.
     *
     * @return array|null
     */
    public function getJsonContent(): ?array
    {
        if (!$this->response) {
            return null;
        }

        $content = $this->response->getContent();
        return json_decode($content, true);
    }

    /**
     * Get the raw response.
     *
     * @return Response|null
     */
    public function getResponse(): ?Response
    {
        return $this->response;
    }

    /**
     * Get the response status code.
     *
     * @return int|null
     */
    public function getStatusCode(): ?int
    {
        return $this->response ? $this->response->getStatusCode() : null;
    }
}
