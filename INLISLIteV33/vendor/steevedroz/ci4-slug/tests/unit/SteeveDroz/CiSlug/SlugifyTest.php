<?php

namespace SteeveDroz\CiSlug;

use Mocks\MockModelConvention;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \SteeveDroz\CiSlug\Slugify
 */
class SlugifyTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->model = new MockModelConvention();
    }

    public function testConstructor()
    {
        $slugify = new Slugify($this->model);
        $this->assertInstanceOf(\SteeveDroz\CiSlug\Slugify::class, $slugify);
    }

    public function testAddSlugStandard()
    {
        $slugify = new Slugify($this->model);
        $data = ['data' => ['name' => 'Great news, everyone!']];
        $data = $slugify->addSlug($data, 'name');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data',$data);
        $this->assertIsArray($data['data']);
        $this->assertArrayHasKey('name',$data['data']);
        $this->assertSame('Great news, everyone!', $data['data']['name']);
        $this->assertArrayHasKey('slug',$data['data']);
        $this->assertSame('great-news-everyone', $data['data']['slug']);
    }

    public function testAddSlugMissingField()
    {
        $slugify=new Slugify($this->model);
        $data = ['data' => ['not_name' => 'Great news, everyone!']];
        $data = $slugify->addSlug($data, 'name');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data',$data);
        $this->assertIsArray($data['data']);
        $this->assertArrayHasKey('not_name',$data['data']);
        $this->assertSame('Great news, everyone!', $data['data']['not_name']);
        $this->assertFalse(array_key_exists('slug',$data['data']));
    }

    public function testAddSlugNotUnique()
    {
        $slugify = new Slugify($this->model);
        $data = ['data' => ['name' => 'That\'s all, folks!']];
        $data = $slugify->addSlug($data, 'name');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data',$data);
        $this->assertIsArray($data['data']);
        $this->assertArrayHasKey('name',$data['data']);
        $this->assertSame('That\'s all, folks!', $data['data']['name']);
        $this->assertArrayHasKey('slug',$data['data']);
        $this->assertSame('thats-all-folks-2', $data['data']['slug']);

        $data = ['data' => ['name' => 'Bowties are cool!!!']];
        $data = $slugify->addSlug($data, 'name');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data',$data);
        $this->assertIsArray($data['data']);
        $this->assertArrayHasKey('name',$data['data']);
        $this->assertSame('Bowties are cool!!!', $data['data']['name']);
        $this->assertArrayHasKey('slug',$data['data']);
        $this->assertSame('bowties-are-cool-3', $data['data']['slug']);
    }

    public function testAddSlugWithConfiguration()
    {
        $slugify=new Slugify(new \Mocks\MockModelConfiguration());
        $slugify->setField('uri_code');

        $data = ['data' => ['name' => 'That\'s all, folks!']];
        $data = $slugify->addSlug($data, 'name');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data',$data);
        $this->assertIsArray($data['data']);
        $this->assertArrayHasKey('name',$data['data']);
        $this->assertSame('That\'s all, folks!', $data['data']['name']);
        $this->assertArrayHasKey('uri_code',$data['data']);
        $this->assertSame('thats-all-folks-2', $data['data']['uri_code']);
        $this->assertFalse(array_key_exists('slug',$data['data']));
    }
}
