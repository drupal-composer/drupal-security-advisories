<?php

declare(strict_types = 1);

namespace App\Tests;

use App\DTO\Release;
use PHPUnit\Framework\TestCase;

class ReleaseTest extends TestCase
{
    public function testValidation(): void
    {
        $item = [
            'body' => ['value' => 'Test'],
            'type' => 'insecure',
            'name' => 'test',
            'field_release_project' => ['id' => '12345'],
            'field_release_version' => '8.x-1.x',
        ];

        $dto = Release::createFromArray($item, 'test', 'insecure');
        $this->assertInstanceOf(Release::class, $dto);
        $this->assertEquals($item['body']['value'], $dto->getBody());
        $this->assertEquals($item['type'], $dto->getType());
        $this->assertEquals($item['name'], $dto->getName());
        $this->assertEquals($item['field_release_project']['id'], $dto->getProject());
        $this->assertEquals($item['field_release_version'], $dto->getVersion());
    }
}
