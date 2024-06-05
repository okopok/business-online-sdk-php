<?php

namespace bru\api\Http;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

final class ServerRequest implements ServerRequestInterface
{
	use MessageTrait;

    private string|null $requestTarget = null;
    private array $attributes = [];

    public function __construct(
        private array $serverParams = [],
        private array $uploadedFiles = [],
        private array $cookieParams = [],
        private array $queryParams = [],
        private mixed $parsedBody = null,
        private string $method = 'GET',
        private UriInterface|string|null $uri = null,
        private array $headers = [],
        private mixed $body = null,
        private string $protocol = '1.1',
	)
	{
		$this->validateUploadedFiles($uploadedFiles);
        if ($uri === null) {
            $this->setUri($uri);
        }

		$this->registerStream($body);
		$this->registerHeaders($headers);
		$this->registerProtocolVersion($protocol);

		if (!$this->hasHeader('host')) {
			$this->updateHostHeaderFromUri();
		}
	}

	/**
	 * @return string
	 */
	public function getRequestTarget(): string
	{
		if ($this->requestTarget !== null) {
			return $this->requestTarget;
		}

		$target = $this->uri->getPath();
		$query = $this->uri->getQuery();

		if ($target !== '' && $query !== '') {
			$target .= '?' . $query;
		}

		return $target ?: '/';
	}

	/**
	 * @param mixed $requestTarget
	 * @return $this
	 */
    public function withRequestTarget($requestTarget): self
	{
		if ($requestTarget === $this->requestTarget) {
			return $this;
		}

		if (!is_string($requestTarget) || preg_match('/\s/', $requestTarget)) {
			throw new InvalidArgumentException(sprintf(
				'Неверная цель запроса - "%s". Цель запроса должна быть строкой и не может содержать пробелы',
				(is_object($requestTarget) ? get_class($requestTarget) : gettype($requestTarget))
			));
		}

		$new = clone $this;
		$new->requestTarget = $requestTarget;
		return $new;
	}

	/**
	 * @return string
	 */
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * @param string $method
     *
     * @return RequestInterface
     */
    public function withMethod(string $method): RequestInterface
	{
		if ($method === $this->method) {
			return $this;
		}

		$new = clone $this;
		$new->method = $method;
		return $new;
	}

    public function getUri(): UriInterface
    {
		return $this->uri;
	}

	/**
	 * @param UriInterface $uri
	 * @param false $preserveHost
	 * @return $this
	 */
    public function withUri(UriInterface $uri, $preserveHost = false): RequestInterface
	{
		if ($uri === $this->uri) {
			return $this;
		}

		$new = clone $this;
		$new->uri = $uri;

		if (!$preserveHost || !$this->hasHeader('host')) {
			$new->updateHostHeaderFromUri();
		}

		return $new;
	}

	/**
	 * @return array
	 */
	public function getServerParams(): array
	{
		return $this->serverParams;
	}

	/**
	 * @return array
	 */
	public function getCookieParams(): array
	{
		return $this->cookieParams;
	}

	/**
	 * @param array $cookies
	 * @return ServerRequest
	 */
    public function withCookieParams(array $cookies): self
	{
		$new = clone $this;
		$new->cookieParams = $cookies;
		return $new;
	}

	/**
	 * @return array
	 */
	public function getQueryParams(): array
	{
		return $this->queryParams;
	}

	/**
	 * @param array $query
	 * @return ServerRequest
	 */
    public function withQueryParams(array $query): self
	{
		$new = clone $this;
		$new->queryParams = $query;
		return $new;
	}

	/**
	 * @return array
	 */
	public function getUploadedFiles(): array
	{
		return $this->uploadedFiles;
	}

	/**
	 * @param array $uploadedFiles
	 * @return ServerRequest
	 */
    public function withUploadedFiles(array $uploadedFiles): self
	{
		$this->validateUploadedFiles($uploadedFiles);
		$new = clone $this;
		$new->uploadedFiles = $uploadedFiles;
		return $new;
	}

	/**
	 * @return array|mixed|object|null
	 */
	public function getParsedBody()
	{
		return $this->parsedBody;
	}

	/**
	 * @param array|object|null $data
	 * @return ServerRequest
	 */
    public function withParsedBody($data): self
	{
		if (!is_array($data) && !is_object($data) && $data !== null) {
			throw new InvalidArgumentException(sprintf(
				'Неверныe параметры запроса - "%s". Параметры запроса должны быть объектом, массивом либо null.',
				gettype($data)
			));
		}

		$new = clone $this;
		$new->parsedBody = $data;
		return $new;
	}

    public function getAttributes(): array
    {
		return $this->attributes;
	}

	/**
	 * @param string $name
	 * @param null $default
     *
	 * @return mixed|null
	 */
    public function getAttribute(string $name, $default = null): mixed
    {
		if (array_key_exists($name, $this->attributes)) {
			return $this->attributes[$name];
		}

		return $default;
	}

	/**
	 * @param string $name
	 * @param mixed $value
     *
	 * @return $this
	 */
    public function withAttribute(string $name, mixed $value): self
	{
		if (array_key_exists($name, $this->attributes) && $this->attributes[$name] === $value) {
			return $this;
		}

		$new = clone $this;
		$new->attributes[$name] = $value;
		return $new;
	}

	/**
	 * @param string $name
     *
	 * @return $this
	 */
    public function withoutAttribute(string $name): self
	{
		if (!array_key_exists($name, $this->attributes)) {
			return $this;
		}

		$new = clone $this;
		unset($new->attributes[$name]);
		return $new;
	}

    private function setUri(UriInterface|string $uri): void
	{
		if ($uri instanceof UriInterface) {
			$this->uri = $uri;
			return;
		}
        $this->uri = new Uri($uri);
	}

	private function updateHostHeaderFromUri(): void
	{
		$host = $this->uri->getHost();

		if ($host === '') {
			return;
		}

		if ($port = $this->uri->getPort()) {
			$host .= ':' . $port;
		}

		$this->headerNames['host'] = 'Host';
		$this->headers = [$this->headerNames['host'] => [$host]] + $this->headers;
	}

	/**
	 * @param array $uploadedFiles
	 */
	private function validateUploadedFiles(array $uploadedFiles): void
	{
		foreach ($uploadedFiles as $file) {
			if (is_array($file)) {
				$this->validateUploadedFiles($file);
				continue;
			}

            if (!($file instanceof UploadedFileInterface)) {
				throw new InvalidArgumentException(sprintf(
					'Неверный объект в структуре загружаемых файлов.'
					. '"%s" не реализует интерфейс "\Psr\Http\Message\UploadedFileInterface".',
					(is_object($file) ? get_class($file) : gettype($file))
				));
			}
		}
	}
}