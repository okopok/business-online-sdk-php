<?php

namespace bru\api\Http;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use function get_class;
use function gettype;
use function implode;
use function in_array;
use function is_float;
use function is_numeric;
use function is_object;
use function is_string;
use function ltrim;
use function parse_url;
use function preg_replace;
use function preg_replace_callback;
use function rawurlencode;
use function sprintf;
use function strtolower;

final class Uri implements UriInterface
{
    /*
     * Стандартные порты
     */
    private const SCHEMES = [80 => 'http', 443 => 'https'];

    /**
     * @var string
     */
    private string $scheme = '';

    /**
     * @var string
     */
    private string $userInfo = '';

    /**
     * @var string
     */
    private string $host = '';

    /**
     * @var int|null
     */
    private mixed $port = null;

    /**
     * @var string
     */
    private string $path = '';

    /**
     * @var string
     */
    private string $query = '';

    /**
     * @var string
     */
    private string $fragment = '';

    /**
     * @var string|null
     */
    private string|null $cache;

    /**
     * Uri constructor.
     *
     * @param string $uri
     */
    public function __construct(string $uri = '')
    {
        if ($uri === '') {
            return;
        }

        if (($uri = parse_url($uri)) === false) {
            throw new InvalidArgumentException('Неверный формат строки запроса');
        }

        $this->scheme = isset($uri['scheme']) ? $this->normalizeScheme($uri['scheme']) : '';
        $this->userInfo = isset($uri['user']) ? $this->normalizeUserInfo($uri['user'], $uri['pass'] ?? null) : '';
        $this->host = isset($uri['host']) ? $this->normalizeHost($uri['host']) : '';
        $this->port = isset($uri['port']) ? $this->normalizePort($uri['port']) : null;
        $this->path = isset($uri['path']) ? $this->normalizePath($uri['path']) : '';
        $this->query = isset($uri['query']) ? $this->normalizeQuery($uri['query']) : '';
        $this->fragment = isset($uri['fragment']) ? $this->normalizeFragment($uri['fragment']) : '';
    }

    /**
     * @param string $scheme
     *
     * @return string
     */
    private function normalizeScheme(string $scheme): string
    {
        if (!$scheme = preg_replace('#:(//)?$#', '', strtolower($scheme))) {
            return '';
        }

        if (!in_array($scheme, self::SCHEMES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Протокол "%s" не поддерживается. Он должен быть пуст либо быть в списке: "%s".',
                    $scheme,
                    implode('", "', self::SCHEMES),
                ),
            );
        }

        return $scheme;
    }

    /**
     * @param string $user
     * @param string|null $pass
     *
     * @return string
     */
    private function normalizeUserInfo(string $user, string|null $pass = null): string
    {
        if ($user === '') {
            return '';
        }

        $pattern = '/(?:[^a-zA-Z0-9_\-\.~!\$&\'\(\)\*\+,;=]+|%(?![A-Fa-f0-9]{2}))/u';
        $userInfo = $this->encode($user, $pattern);

        if ($pass !== null) {
            $userInfo .= ':'.$this->encode($pass, $pattern);
        }

        return $userInfo;
    }

    private function encode(string $string, string $pattern): string
    {
        return (string) preg_replace_callback(
            $pattern,
            static function ($m) {
                return rawurlencode($m[0]);
            },
            $string,
        );
    }

    /**
     * @param string $host
     *
     * @return string
     */
    private function normalizeHost(string $host): string
    {
        return strtolower($host);
    }

    /**
     * @param mixed $port
     *
     * @return int|null
     */
    private function normalizePort(mixed $port): ?int
    {
        if ($port === null) {
            return null;
        }

        if (!is_numeric($port) || is_float($port)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Задан неверный порт -  "%s". Порт должен быть числом, числовой строкой либо null.',
                    (is_object($port) ? get_class($port) : gettype($port)),
                ),
            );
        }

        $port = (int) $port;

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException(
                sprintf(
                    'Задан неверный порт -  "%d". Он должен соответствовать одному из TCP/UDP портов и быть в диапазоне от 2 до 65534.',
                    $port,
                ),
            );
        }

        return $port;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function normalizePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return $path;
        }

        $path = $this->encode($path, '/(?:[^a-zA-Z0-9_\-\.~:@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/');
        if ($path === '') {
            return '';
        }
        if ($path[0] === '/') {
            return '/'.ltrim($path, '/');
        }
        return $path;
    }

    /**
     * @param string $query
     *
     * @return string
     */
    private function normalizeQuery(string $query): string
    {
        if ($query === '' || $query === '?') {
            return '';
        }

        if ($query[0] === '?') {
            $query = ltrim($query, '?');
        }

        return $this->encode($query, '/(?:[^a-zA-Z0-9_\-\.~!\$&\'\(\)\*\+,;=%:@\/\?]+|%(?![A-Fa-f0-9]{2}))/');
    }

    /**
     * @param string $fragment
     *
     * @return string
     */
    private function normalizeFragment(string $fragment): string
    {
        if ($fragment === '' || $fragment === '#') {
            return '';
        }

        if ($fragment[0] === '#') {
            $fragment = ltrim($fragment, '#');
        }

        return $this->encode($fragment, '/(?:[^a-zA-Z0-9_\-\.~!\$&\'\(\)\*\+,;=%:@\/\?]+|%(?![A-Fa-f0-9]{2}))/');
    }

    public function __clone()
    {
        $this->cache = null;
    }

    /**
     * @return string
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * @return string
     */
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int|null
     */
    public function getPort(): int|null
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @return string
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * @param string $scheme
     *
     * @return $this
     */
    public function withScheme(string $scheme): self
    {
        $this->checkStringType($scheme, 'scheme', __METHOD__);
        $schema = $this->normalizeScheme($scheme);

        if ($schema === $this->scheme) {
            return $this;
        }

        $new = clone $this;
        $new->scheme = $schema;
        return $new;
    }

    /**
     * @param $value
     * @param string $phrase
     * @param string $method
     */
    private function checkStringType($value, string $phrase, string $method): void
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                sprintf(
                    '"%s" method expects a string type %s. "%s" received.',
                    $method,
                    $phrase,
                    (is_object($value) ? get_class($value) : gettype($value)),
                ),
            );
        }
    }

    /**
     * @param string $user
     * @param null $password
     *
     * @return $this
     */
    public function withUserInfo(string $user, $password = null): self
    {
        $this->checkStringType($user, 'user', __METHOD__);

        if ($password !== null) {
            $this->checkStringType($password, 'or null password', __METHOD__);
        }

        $userInfo = $this->normalizeUserInfo($user, $password);

        if ($userInfo === $this->userInfo) {
            return $this;
        }

        $new = clone $this;
        $new->userInfo = $userInfo;
        return $new;
    }

    /**
     * @param string $host
     *
     * @return $this
     */
    public function withHost(string $host): self
    {
        $this->checkStringType($host, 'host', __METHOD__);
        $host = $this->normalizeHost($host);

        if ($host === $this->host) {
            return $this;
        }

        $new = clone $this;
        $new->host = $host;
        return $new;
    }

    /**
     * @param int|null $port
     *
     * @return $this
     */
    public function withPort(int|null $port): self
    {
        $port = $this->normalizePort($port);

        if ($port === $this->port) {
            return $this;
        }

        $new = clone $this;
        $new->port = $port;
        return $new;
    }

    /**
     * @param string $path
     *
     * @return $this
     */
    public function withPath(string $path): self
    {
        $this->checkStringType($path, 'path', __METHOD__);
        $path = $this->normalizePath($path);

        if ($path === $this->path) {
            return $this;
        }

        $new = clone $this;
        $new->path = $path;
        return $new;
    }

    /**
     * @param string $query
     *
     * @return $this
     */
    public function withQuery(string $query): self
    {
        $this->checkStringType($query, 'query string', __METHOD__);
        $query = $this->normalizeQuery($query);

        if ($query === $this->query) {
            return $this;
        }

        $new = clone $this;
        $new->query = $query;
        return $new;
    }

    /**
     * @param string $fragment
     *
     * @return $this
     */
    public function withFragment(string $fragment): self
    {
        $this->checkStringType($fragment, 'URI fragment', __METHOD__);
        $fragment = $this->normalizeFragment($fragment);

        if ($fragment === $this->fragment) {
            return $this;
        }

        $new = clone $this;
        $new->fragment = $fragment;
        return $new;
    }

    public function __toString(): string
    {
        if (is_string($this->cache)) {
            return $this->cache;
        }

        $this->cache = '';

        if ($this->scheme !== '') {
            $this->cache .= $this->scheme.':';
        }

        if (($authority = $this->getAuthority()) !== '') {
            $this->cache .= '//'.$authority;
        }

        if ($this->path !== '') {
            $this->cache .= $authority ? '/'.ltrim($this->path, '/') : $this->path;
        }

        if ($this->query !== '') {
            $this->cache .= '?'.$this->query;
        }

        if ($this->fragment !== '') {
            $this->cache .= '#'.$this->fragment;
        }

        return $this->cache;
    }

    public function getAuthority(): string
    {
        if (($authority = $this->host) === '') {
            return '';
        }

        if ($this->userInfo !== '') {
            $authority = $this->userInfo.'@'.$authority;
        }

        if ($this->isNotStandardPort()) {
            $authority .= ':'.$this->port;
        }

        return $authority;
    }

    /**
     * @return bool
     */
    private function isNotStandardPort(): bool
    {
        if ($this->port === null) {
            return false;
        }

        return (!isset(self::SCHEMES[$this->port]) || $this->scheme !== self::SCHEMES[$this->port]);
    }
}