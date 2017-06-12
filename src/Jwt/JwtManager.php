<?php

namespace PhpSolution\JwtBundle\Jwt;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\InvalidTokenException;
use PhpSolution\JwtBundle\Jwt\Configuration\ConfigRegistry;
use PhpSolution\JwtBundle\Jwt\Type\TypeConfigInterface;
use PhpSolution\JwtBundle\Jwt\Type\TypeInterface;
use PhpSolution\JwtBundle\Jwt\Type\TypeRegistry;

/**
 * Class JwtTokenManager
 */
class JwtManager
{
    /**
     * @var ConfigRegistry
     */
    private $configRegistry;
    /**
     * @var TypeRegistry
     */
    private $typeRegistry;

    /**
     * JwtManager constructor.
     *
     * @param ConfigRegistry $configRegistry
     * @param TypeRegistry   $typeRegistry
     */
    public function __construct(ConfigRegistry $configRegistry, TypeRegistry $typeRegistry)
    {
        $this->configRegistry = $configRegistry;
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * @param string   $typeName
     * @param iterable $claims
     * @param iterable $headers
     *
     * @return Token\Plain
     */
    public function create(string $typeName, iterable $claims = [], iterable $headers = []): Token\Plain
    {
        /* @var $type TypeInterface|TypeConfigInterface */
        $type = $this->typeRegistry->getTypeByName($typeName);
        $config = $this->getConfigurationByType($type);
        $builder = $config->createBuilder();
        foreach ($claims as $claimName => $claimValue) {
            $builder->withClaim($claimName, $claimValue);
        }
        foreach ($headers as $headerName => $headerValue) {
            $builder->withHeader($headerName, $headerValue);
        }
        $type->configureBuilder($builder);

        return $builder->getToken($config->getSigner(), $config->getSigningKey());
    }

    /**
     * @param string $jwt
     * @param string $typeName
     *
     * @return Token
     *
     * @throws InvalidTokenException
     */
    public function parse(string $jwt, string $typeName): Token
    {
        $type = $this->typeRegistry->getTypeByName($typeName);
        $config = $this->getConfigurationByType($type);
        $token = $config->getParser()->parse($jwt);

        $constraints = $type->getConstraints($config);
        if (is_iterable($constraints)) {
            $config->getValidator()->assert($token, ...$constraints);
        }

        return $token;
    }

    /**
     * @param TypeInterface $type
     *
     * @return Configuration
     */
    private function getConfigurationByType(TypeInterface $type): Configuration
    {
        return $type instanceof TypeConfigInterface && !empty($configName = $type->getConfigurationName())
            ? $this->configRegistry->getConfiguration($configName)
            : $this->configRegistry->getDefaultConfiguration();
    }
}