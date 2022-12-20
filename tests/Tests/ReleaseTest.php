<?php

declare(strict_types = 1);

namespace App\Tests;

use App\DTO\Release;
use PHPUnit\Framework\TestCase;

class ReleaseTest extends TestCase
{
    private function assertDto(array $values): void
    {
        $dto = Release::createFromArray($values, 'test', 'insecure');

        $this->assertInstanceOf(Release::class, $dto);
        $this->assertEquals($values['body']['value'], $dto->getBody());
        $this->assertEquals($values['type'], $dto->getType());
        $this->assertEquals($values['name'], $dto->getName());
        $this->assertEquals($values['field_release_project']['id'], $dto->getProject());
        $this->assertEquals($values['field_release_version'], $dto->getVersion());
    }

    public function testValidation(): void
    {
        $item = [
            'body' => ['value' => 'Test'],
            'type' => 'insecure',
            'name' => 'test',
            'field_release_project' => ['id' => '12345'],
            'field_release_version' => '8.x-1.x',
        ];
        $this->assertDto($item);
    }

    public function testOptionalValues(): void
    {
        $item = [
            'body' => ['value' => 'Test'],
            'type' => 'insecure',
            'name' => 'test',
            'field_release_project' => ['id' => null],
            'field_release_version' => '8.x-1.x',
        ];
        $this->assertDto($item);
    }
}
