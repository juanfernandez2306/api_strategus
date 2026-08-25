<?php

declare(strict_types=1);

namespace Tests\Application\Actions\User\Register;

use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Validators\Auth\RegisterValidator;
use DI\ContainerBuilder;
use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

class RegisterValidatorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<UserRepositoryInterface> */
    private ObjectProphecy $userRepositoryProphecy;

    private Generator $faker;
    private RegisterValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create('es_ES');


        /** @var ObjectProphecy<UserRepositoryInterface> $this->userRepositoryProphecy */
        $this->userRepositoryProphecy = $this->prophesize(UserRepositoryInterface::class);


        $builder = new ContainerBuilder();
        $builder->addDefinitions([
            UserRepositoryInterface::class => $this->userRepositoryProphecy->reveal(),
        ]);

        $container = $builder->build();

        // 3. PHP-DI resuelve e inyecta RegisterValidator automáticamente sin hardcode
        $this->validator = $container->get(RegisterValidator::class);
    }

    private function makeValidPayload(array $overrides = []): array
    {
        $firstName = $this->faker->regexify('[A-Za-zÑñ]{6}');
        $lastName  = $this->faker->regexify('[A-Za-zÑñ]{8}');
        $password = $this->faker->regexify('[A-Za-z]{4}[0-9]{2}') . '!';

        return array_merge([
            'first_name'            => $firstName,
            'last_name'             => $lastName,
            'email'                 => $this->faker->unique()->safeEmail(),
            'password'              => $password,
            'password_confirmation' => $password,
        ], $overrides);
    }

    public function testValidatesRegistrationWithFakedData(): void
    {
        $payload = $this->makeValidPayload();

        $this->userRepositoryProphecy
            ->existsByEmail($payload['email'])
            ->willReturn(false);

        $this->userRepositoryProphecy
            ->countActiveUsers()
            ->willReturn(0);

        // Usamos la instancia resuelta por el contenedor
        $result = $this->validator->validate($payload);

        $this->assertEquals($payload['email'], $result['email']);
    }
}
