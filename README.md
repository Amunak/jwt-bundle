# JwtBundle
This bundle allows developer to use "[lcobucci/jwt](https://github.com/lcobucci/jwt)" lib for work with JWT.

## Configure JWT Configuration
````YAML
jwt:
  default_configuration: 'default'
  configurations:
    default: # name 
      asymmetric: true
      signer:
        class: 'Lcobucci\JWT\Signer\Rsa\Sha512'
      signing_key:
        content: 'file://%kernel.project_dir%/etc/jwt/keys/private.pem'
        pass: 'test'
      verification_key:
        content: 'file://%kernel.project_dir%/etc/jwt/keys/public.pub'
````

If you want use signer, signing_key, verification_key as DI service use this example: 
````YAML
jwt:
  default_configuration: 'default'
  configurations:
    default: # name 
      signer:
        service_id: 'jwt_signer_service_id'
      signing_key: 'jwt_signing_key_service_id'
      verification_key: 'jwt_verification_key_service_id'
````
## Generate the JWT keys
````Bash
$ mkdir -p config/jwt
$ openssl genrsa -out config/jwt/private.pem -aes256 4096
$ openssl rsa -pubout -in config/jwt/private.pem -out var/jwt/public.pem
````


## Configure JWT Types on config.yaml
You can specify JWT Type on your basic config.yaml.
If configuration is null, system set default configuration
````YAML
jwt:  
  types:
    authorization: #name of type
      configuration: 'default'
      options:
        exr: 0
        issued_at: 0
        used_after: 0
        claims: []
        headers: []
        issuer: ''
        id: ''
        audience: ''
        subject: ''
````
using on controller:
````PHP
<?php
/**
 * Class UserConfirm
 */
class UserConfirmController extends Controller
{
    public function sendLinkAction(): Response
    {
        /* @var $token \Lcobucci\JWT\Token\Plain */
        $token = $this->get('jwt.manager')->create('authorization', ['claim' => 'value']);
        $jwtStr = $token->__toString();
    }
    
    public function confirmAction(string $token): Response
    {
        /* @var $token \Lcobucci\JWT\Token\Plain */
        $token = $this->get('jwt.manager')->parse($token, 'authorization');
        $userId = $token->claims()->get('user_id');
    }
}
````


## Specify JWT Type as a service

Create the token type, making sure *at the very least* the *SignedWith* constraint
is returned by `getConstraints` - otherwise your token will be unsafe and not verified:

````PHP
// src/Service/UserConfirm.php

use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Builder as BuilderInterface;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Validation\Constraint;
use PhpSolution\JwtBundle\Jwt\Type\TypeInterface;

class UserConfirm implements TypeInterface
{
    private const EXP_TIME = 3600;
    public const NAME = 'user_confirm_registration';

    public function getName(): string
    {
        return self::NAME;
    }

    public function configureBuilder(BuilderInterface $builder): void
    {
        $builder->expiresAt(new \DateTimeImmutable('+' . self::EXP_TIME . 'second'));
    }

    public function getConstraints(Configuration $config):? iterable
    {
        yield new Constraint\SignedWith($config->getSigner(), $config->getVerificationKey());
        yield new Constraint\ValidAt(SystemClock::fromSystemTimezone());
    }
}
````

If you use autoconfiguration, implementing the TypeInterface automatically tags the service for you.
Otherwise tag the service manually:

````YAML
// config/services.yaml
services:
    jwt.type.user_confirm_registration:
        class: 'App\Services\JwtType\UserConfirmReg'
        tags: ['jwt.token_type']
````

Then use it somewhere - like in a controller:

````PHP
<?php
// src/Controller/UserConfirmController.php

use App\Services\JwtType\UserConfirm;
use PhpSolution\JwtBundle\Jwt\TokenManagerInterface;

class UserConfirmController extends Controller
{
    private TokenManagerInterface $tokenManager;

    public function __construct(TokenManagerInterface $tokenManager){
        $this->tokenManager = $tokenManager;
    }

    public function sendLinkAction(): Response
    {
        $token = $this->tokenManager->create(UserConfirm::NAME, ['user_id' => $userId]);
        $jwtStr = $token->toString(); // this is your encoded JWT token
    }

    public function confirmAction(string $token): Response
    {
        $token = $this->tokenManager->parseTokenWithClaims($token, UserConfirm::NAME, ['user_id']);
        $userId = $token->claims()->get('user_id');
    }
}
````

## Full Default Configuration
````YAML
jwt:
  default_configuration: 'default'
  configurations:
    default:
      asymmetric: true
      signer:
        service_id: ~
        class: 'Lcobucci\JWT\Signer\Rsa\Sha512'
      signing_key:
        service_id: ~
        content: ~
        pass: ~
      verification_key:
        service_id: ~
        content: ~
  types:
    authorization:
      configuration: 'default'
      options:
        exr: ~
        issued_at: ~
        used_after: ~
        claims: []
        headers: []
        issuer: ~
        id: ~
        audience: ~
        subject: ~
````
