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

    public function testLoad()
    {
        static::assertEquals($this->directive, new Directive($this->configuration));
    }

    public function testDirective()
    {
        static::assertContainsOnlyInstancesOf(Directive::class, [
            $this->directive,
            $this->directive->get('server'),
            $this->directive->server->get('server_name'),
            $this->directive->server->serverName,
            $this->directive->server->server_name,
        ]);

        static::assertTrue($this->directive->server->hasName());

        static::assertNull($this->directive->get('undefined_directive'));

        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage("Undefined directive 'undefined_directive'");

        $this->directive->undefinedDirective;
    }

    public function testDirectives()
    {
        $server = $this->directive->server;

        static::assertTrue($server->hasChildren());
        static::assertFalse($server->index->hasChildren());

        static::assertTrue($server->has('server_name'));

        static::assertCount(4, $server->children('location'));
        static::assertEquals(4, $server->location->count());

        static::assertContainsOnlyInstancesOf(Directive::class, $server->children('listen')->all());
        static::assertContainsOnlyInstancesOf(Directive::class, $server->location->all());

        static::assertCount(3, $server->search('fastcgi_param'));

        static::assertInstanceOf(Directive::class, $server->find('listen'));
        static::assertNull($server->find('undefinedDirective'));

        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage("Undefined collection method 'undefinedMethod'");

        $this->directive->get('server')->undefinedMethod();
    }

    public function testNoParent()
    {
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage("Undefined method 'undefinedMethod'");

        $this->directive->undefinedMethod();
    }

    public function testValue()
    {
        static::assertTrue($this->directive->server->serverName->hasValue());
        static::assertFalse($this->directive->server->hasValue());
        static::assertEquals('server.dev', $this->directive->server->serverName->value());

        $this->directive->server->serverName->setValue(null);
        static::assertEquals(null, $this->directive->server->serverName->value());
    }

    public function testComment()
    {
        static::assertTrue($this->directive->server->location->hasComment());

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
            $this->directive->server->location->get('try_files')->comment()
        );

        static::assertEquals(
            'fourth comment',
            $this->directive->server->children()->slice(10, 1)->first()->comment()
        );

        $this->directive->server->location->setComment(null);

        static::assertEquals(null, $this->directive->server->location->comment());
    }

    public function testMoveDirective()
    {
        static::assertCount(4, $this->directive->server->children('location'));

        $directive = $this->directive->server->get('location');

        $this->directive->server->remove($directive);

        static::assertCount(3, $this->directive->server->children('location'));

        $this->directive->server->add($directive);

        static::assertCount(4, $this->directive->server->children('location'));

        $this->directive->server->remove($directive);

        $this->directive->server->prepend($directive);

        static::assertEquals($directive, $this->directive->server->location);
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
