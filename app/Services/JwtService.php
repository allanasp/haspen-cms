<?php

declare(strict_types=1);

namespace App\Services;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;

/**
 * JWT Service for handling JSON Web Token operations.
 * @psalm-suppress UnusedClass
 */
final class JwtService extends BaseService
{
    private Configuration $config;

    private string $issuer;

    private string $audience;

    private int $ttl;

    private int $refreshTtl;

    public function __construct()
    {
        $secret = (string) config('app.jwt_secret', config('app.key'));
        $this->issuer = (string) config('app.url');
        $this->audience = (string) config('app.url');
        $this->ttl = (int) config('app.jwt_ttl', 3600); // 1 hour default
        $this->refreshTtl = (int) config('app.jwt_refresh_ttl', 1209600); // 2 weeks default

        if (empty($secret)) {
            throw new \InvalidArgumentException('JWT secret cannot be empty');
        }
        
        $this->config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($secret)
        );
    }

    /**
     * Generate an access token for a user.
     *
     * @param array<string, mixed> $claims
     */
    public function generateAccessToken(int|string $userId, array $claims = []): string
    {
        $now = new \DateTimeImmutable();

        if (empty($this->issuer) || empty($this->audience)) {
            throw new \InvalidArgumentException('Issuer and audience must be configured');
        }
        
        $builder = $this->config->builder()
            ->issuedBy($this->issuer)
            ->permittedFor($this->audience)
            ->relatedTo($userId !== '' ? (string) $userId : '0')
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify("+{$this->ttl} seconds"))
            ->withClaim('type', 'access');

        // Add custom claims
        foreach ($claims as $name => $value) {
            if (!is_string($name) || empty($name)) {
                continue;
            }
            $builder = $builder->withClaim($name, $value);
        }

        $token = $builder->getToken($this->config->signer(), $this->config->signingKey());

        /** @var \DateTimeImmutable|null $exp */
        $exp = $token->claims()->get('exp');
        $this->logInfo('Access token generated', [
            'user_id' => $userId,
            'expires_at' => $exp instanceof \DateTimeImmutable ? $exp->format('Y-m-d H:i:s') : null,
        ]);

        return $token->toString();
    }

    /**
     * Generate a refresh token for a user.
     */
    public function generateRefreshToken(int|string $userId): string
    {
        $now = new \DateTimeImmutable();

        if (empty($this->issuer) || empty($this->audience)) {
            throw new \InvalidArgumentException('Issuer and audience must be configured');
        }
        
        $token = $this->config->builder()
            ->issuedBy($this->issuer)
            ->permittedFor($this->audience)
            ->relatedTo($userId !== '' ? (string) $userId : '0')
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify("+{$this->refreshTtl} seconds"))
            ->withClaim('type', 'refresh')
            ->getToken($this->config->signer(), $this->config->signingKey());

        /** @var \DateTimeImmutable|null $exp */
        $exp = $token->claims()->get('exp');
        $this->logInfo('Refresh token generated', [
            'user_id' => $userId,
            'expires_at' => $exp instanceof \DateTimeImmutable ? $exp->format('Y-m-d H:i:s') : null,
        ]);

        return $token->toString();
    }

    /**
     * Parse and validate a token.
     */
    public function parseToken(string $tokenString): Plain
    {
        try {
            if (empty($tokenString)) {
                throw new \InvalidArgumentException('Token string cannot be empty');
            }
            $token = $this->config->parser()->parse($tokenString);

            if (! $token instanceof Plain) {
                throw new \InvalidArgumentException('Invalid token format');
            }

            return $token;
        } catch (\Exception $e) {
            $this->logError('Failed to parse token', [
                'error' => $e->getMessage(),
            ]);

            throw new \InvalidArgumentException('Invalid token: ' . $e->getMessage());
        }
    }

    /**
     * Validate a token.
     */
    public function validateToken(Plain $token): bool
    {
        try {
            if (empty($this->issuer) || empty($this->audience)) {
                throw new \InvalidArgumentException('Issuer and audience must be configured');
            }
            $constraints = [
                new IssuedBy($this->issuer),
                new PermittedFor($this->audience),
            ];

            $this->config->validator()->assert($token, ...$constraints);

            return true;
        } catch (RequiredConstraintsViolated $e) {
            $this->logWarning('Token validation failed', [
                'violations' => array_map(fn ($violation) => $violation->getMessage(), $e->violations()),
            ]);

            return false;
        }
    }

    /**
     * Get user ID from token.
     */
    public function getUserId(Plain $token): string
    {
        /** @var string|null $subject */
        $subject = $token->claims()->get('sub');

        if ($subject === null) {
            throw new \InvalidArgumentException('Token does not contain a subject claim');
        }

        return $subject;
    }

    /**
     * Get token type.
     */
    public function getTokenType(Plain $token): string
    {
        /** @var string|null $type */
        $type = $token->claims()->get('type');

        if ($type === null) {
            throw new \InvalidArgumentException('Token does not contain a type claim');
        }

        return $type;
    }

    /**
     * Get custom claim from token.
     */
    public function getClaim(Plain $token, string $name): mixed
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Claim name cannot be empty');
        }
        return $token->claims()->get($name);
    }

    /**
     * Check if token is expired.
     */
    public function isExpired(Plain $token): bool
    {
        $expiresAt = $token->claims()->get('exp');

        if ($expiresAt === null || !$expiresAt instanceof \DateTimeImmutable) {
            return true;
        }

        return $expiresAt < new \DateTimeImmutable();
    }

    /**
     * Get token expiration time.
     */
    public function getExpirationTime(Plain $token): ?\DateTimeImmutable
    {
        /** @var \DateTimeImmutable|null $exp */
        $exp = $token->claims()->get('exp');
        return $exp instanceof \DateTimeImmutable ? $exp : null;
    }

    /**
     * Refresh an access token using a refresh token.
     */
    /**
     * @param array<string, mixed> $claims
     *
     * @return array<string, int|string>
     */
    public function refreshAccessToken(string $refreshTokenString, array $claims = []): array
    {
        $refreshToken = $this->parseToken($refreshTokenString);

        if (! $this->validateToken($refreshToken)) {
            throw new \InvalidArgumentException('Invalid refresh token');
        }

        if ($this->getTokenType($refreshToken) !== 'refresh') {
            throw new \InvalidArgumentException('Token is not a refresh token');
        }

        $userId = $this->getUserId($refreshToken);

        $newAccessToken = $this->generateAccessToken($userId, $claims);
        $newRefreshToken = $this->generateRefreshToken($userId);

        $this->logInfo('Tokens refreshed', ['user_id' => $userId]);

        return [
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->ttl,
        ];
    }

    /**
     * Create token pair (access + refresh).
     */
    /**
     * @param array<string, mixed> $claims
     *
     * @return array<string, int|string>
     */
    public function createTokenPair(int|string $userId, array $claims = []): array
    {
        $accessToken = $this->generateAccessToken($userId, $claims);
        $refreshToken = $this->generateRefreshToken($userId);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->ttl,
        ];
    }
}
