<?php

namespace Seiler\Directive;

/**
 * Class DirectiveTest
 *
 * @package Seiler\Directive
 */
class DirectiveTest extends \PHPUnit_Framework_TestCase
{
    /** @var  string */
    protected $configuration;

    /** @var  Directive */
    protected $directive;

    /**
     * Sets up the fixture.
     * This method is called before a test is executed.
     */
    public function setUp()
    {
        $this->configuration = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'example.conf');

        $this->directive = Directive::fromString($this->configuration);
    }

    public function testDirective()
    {
        static::assertContainsOnlyInstancesOf(Directive::class, [
            $this->directive,
            $this->directive->children('server')->first(),
            $this->directive->server->children('server_name')->first(),
            $this->directive->server->serverName,
            $this->directive->server->server_name,
        ]);

        static::assertNull($this->directive->children('undefined_directive')->first());

        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage("Undefined directive 'undefined_directive'");

        $this->directive->undefinedDirective;
    }

    public function testDirectives()
    {
        $server = $this->directive->server;

        static::assertTrue($server->hasChildren());
        static::assertFalse($server->index->hasChildren());

        static::assertTrue($server->hasChildren('server_name'));

        static::assertCount(4, $server->children('location'));
        static::assertEquals(4, $server->location->all()->count());
        static::assertCount(4, $server->location->all());

        static::assertCount(1, $server->children('listen', '80'));
        static::assertCount(0, $server->children('listen', '8080'));

        foreach ($server->location->all() as $location) {
            static::assertInstanceOf(Directive::class, $location);
        }

        static::assertContainsOnlyInstancesOf(Directive::class, $server->location->all());
        static::assertInstanceOf(Directive::class, $server->location->all()->get(3));

        static::assertCount(3, $server->children('fastcgi_param', null, true));

        static::assertInstanceOf(Directive::class, $server->children('listen', null, true)->first());
        static::assertNull($server->children('undefinedDirective', null, true)->first());
    }

    public function testValue()
    {
        static::assertNotNull($this->directive->server->serverName->value());
        static::assertNull($this->directive->server->value());
        static::assertEquals('server.dev', $this->directive->server->serverName->value());

        $this->directive->server->serverName->setValue();
        static::assertEquals(null, $this->directive->server->serverName->value());
    }

    public function testComment()
    {
        static::assertNotNull($this->directive->server->location->comment());

        static::assertEquals(
            'first comment',
            $this->directive->server->location->comment()
        );

        static::assertEquals(
            'second comment',
            $this->directive->server->location->children()->first()->comment()
        );

        static::assertEquals(
            'third comment',
            $this->directive->server->location->children('try_files')->first()->comment()
        );

        static::assertEquals(
            'fourth comment',
            $this->directive->server->children()->slice(10, 1)->first()->comment()
        );

        $this->directive->server->location->setComment();

        static::assertEquals(null, $this->directive->server->location->comment());
    }

    public function testMoveDirective()
    {
        static::assertCount(4, $this->directive->server->children('location'));

        $directive = $this->directive->server->location;

        $this->directive->server->detach($directive);

        static::assertCount(3, $this->directive->server->children('location'));

        $this->directive->server->attach($directive);

        static::assertCount(4, $this->directive->server->children('location'));
    }

    public function testRoot()
    {
        static::assertEquals($this->directive, $this->directive->server->location->tryFiles->root());
    }

    public function testTransformations()
    {
        static::assertInternalType('array', $this->directive->toArray());

        static::assertInternalType('array', json_decode($this->directive->toJson(), true));

        static::assertEquals($this->directive, Directive::fromString((string)$this->directive));
    }
}
