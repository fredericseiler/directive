<?php

namespace Seiler\Directive;

use ErrorException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;

/**
 * Class Directive
 *
 * @package Seiler\Directive
 */
class Directive implements Arrayable, Jsonable
{
    /** @var  Directive */
    protected $builder;
    /** @var  string|null */
    public $comment;
    /** @var  Collection */
    public $directives;

    /** @var  string|null */
    public $name;

    /** @var  Directive|null */
    public $parent;

    /** @var  string|null */
    public $value;

    /**
     * Directive constructor.
     *
     * @param string $string
     */
    public function __construct($string = '')
    {
        $this->directives = new Collection;

        if ($string !== '') {
            $this->load($string);
        }
    }

    /**
     * @param  string $method
     * @param  mixed  $arguments
     * @return mixed
     *
     * @throws ErrorException
     */
    public function __call($method, $arguments = null)
    {
        if (!$this->hasParent()) {
            throw new ErrorException("Undefined method '$method'");
        }

        $collection = $this->parent()->children($this->name);

        if (!method_exists($collection, $method)) {
            throw new ErrorException("Undefined collection method '$method'");
        }

        return $collection->$method($arguments);
    }

    /**
     * @param  string $name
     * @return Directive
     *
     * @throws ErrorException
     */
    public function __get($name)
    {
        $name = self::snakeCase($name);

        if ($this->has($name)) {
            return $this->get($name);
        }

        throw new ErrorException("Undefined directive '$name'");
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @param  Directive|null|string $name
     * @param  null|string           $value
     * @param  null|string           $comment
     * @return Directive
     */
    public function add($name = null, $value = null, $comment = null)
    {
        return $this->append($name, $value, $comment);
    }

    /**
     * @param  Directive|null|string $directive
     * @param  null|string           $value
     * @param  null|string           $comment
     * @return Directive
     */
    public function append($directive = null, $value = null, $comment = null)
    {
        if (!$directive instanceof Directive) {
            $name = $directive;

            $directive = new self;
            $directive->setName($name);
            $directive->setValue($value);
            $directive->setComment($comment);
        }

        $directive->setParent($this);

        $this->directives->push($directive);

        return $this;
    }

    /**
     * @param  null|string $name
     * @return Collection
     */
    public function children($name = null)
    {
        $query = $this->directives;

        if ($name !== null) {
            $query = $query->where('name', $name)->values();
        }

        return $query;
    }

    /**
     * @return null|string
     */
    public function comment()
    {
        return $this->comment;
    }

    /**
     * @param  string $configuration
     * @return Directive
     */
    public static function fromString($configuration = '')
    {
        $instance = new static;

        return $instance->load($configuration);
    }

    /**
     * @param  string $name
     * @return Directive|null
     */
    public function get($name)
    {
        return $this->has($name) ? $this->children($name)->first() : null;
    }

    /**
     * @param  string $name
     * @return bool
     */
    public function has($name)
    {
        return $this->directives->contains('name', $name);
    }

    /**
     * @return bool
     */
    public function hasChildren()
    {
        return !$this->directives->isEmpty();
    }

    /**
     * @return bool
     */
    public function hasComment()
    {
        return !empty($this->comment);
    }

    /**
     * @return bool
     */
    public function hasName()
    {
        return !empty($this->name);
    }

    /**
     * @return bool
     */
    public function hasParent()
    {
        return $this->parent !== null;
    }

    /**
     * @return bool
     */
    public function hasValue()
    {
        return !empty($this->value);
    }

    /**
     * @param  string $indent
     * @param  int    $indentSize
     * @return string
     */
    public static function indent($indent = '', $indentSize = 4)
    {
        return $indent . str_repeat(' ', $indentSize);
    }

    /**
     * @return bool
     */
    public function isRoot()
    {
        return !$this->hasParent();
    }

    /**
     * @param  string $configuration
     * @return Directive
     */
    public function load($configuration = '')
    {
        $configuration = str_replace(["\r\n", "\n\r", "\r"], "\n", $configuration);
        $configuration = preg_replace('/  +|\t/', ' ', $configuration);

        $this->builder = $this;

        foreach (explode("\n", $configuration) as $line) {
            $line = trim($line);

            $this->searchForOpeningSection($line)
                ->searchForComment($line)
                ->searchForClosingSection($line)
                ->searchForSingleLine($line);
        }

        $this->name = $this->builder->name();
        $this->value = $this->builder->value();
        $this->comment = $this->builder->comment();
        $this->directives = $this->builder->children();

        return $this;
    }

    /**
     * @return null|string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * @return Directive
     */
    public function parent()
    {
        return $this->parent;
    }

    /**
     * @param  Directive|null|string $name
     * @param  null|string           $value
     * @param  null|string           $comment
     * @return Directive
     */
    public function prepend($name = null, $value = null, $comment = null)
    {
        $this->append($name, $value, $comment);

        $directive = $this->directives->pop();

        $this->directives->prepend($directive);

        return $this;
    }

    /**
     * @param  Directive $directive
     * @return Directive
     */
    public function remove(Directive $directive)
    {
        $key = $this->directives->search($directive, true);

        if ($key !== false) {
            $this->directives->pull($key);
        }

        return $this;
    }

    /**
     * @return Directive
     */
    public function root()
    {
        return $this->isRoot() ? $this : $this->parent()->root();
    }

    /**
     * @param  string $name
     * @return Collection
     */
    public function search($name)
    {
        $directives = new Collection;

        if ($this->has($name)) {
            $directives = $directives->merge($this->children($name));
        }

        if ($this->hasChildren()) {
            $this->directives->each(function (Directive $directive) use ($name, &$directives) {
                $directives = $directives->merge($directive->search($name));
            });
        }

        return $directives;
    }

    /**
     * @param  string $name
     * @return Directive|null
     */
    public function find($name)
    {
        return $this->search($name)->first();
    }

    /**
     * @param  string $line
     * @return Directive
     */
    protected function searchForClosingSection($line)
    {
        if (preg_match('/^}( ?# ?(.*))?$/', $line, $matches)) {
            $this->builder = $this->builder->parent();

            if (array_key_exists(2, $matches)) {
                $this->builder->add(null, null, $matches[2]);
            }
        }

        return $this;
    }

    /**
     * @param  string $line
     * @return Directive
     */
    protected function searchForComment($line)
    {
        if (preg_match('/^# ?(.*)$/', $line, $matches)) {
            $this->builder->add(null, null, $matches[1]);
        }

        return $this;
    }

    /**
     * @param  string $line
     * @return Directive
     */
    protected function searchForOpeningSection($line)
    {
        if (preg_match('/^(\w+) ?(.*?) ?{( ?# ?(.*))?$/', $line, $matches)) {
            $comment = isset($matches[4]) ? $matches[4] : null;

            $this->builder->add($matches[1], $matches[2], $comment);

            $this->builder = $this->builder->children()->last();
        }

        return $this;
    }

    /**
     * @param  string $line
     * @return Directive
     */
    protected function searchForSingleLine($line)
    {
        if (preg_match('/^(\w+) (.+);( ?# ?(.*))?$/', $line, $matches)) {
            $comment = isset($matches[4]) ? $matches[4] : null;

            $this->builder->add($matches[1], $matches[2], $comment);
        }

        return $this;
    }

    /**
     * @param  null|string $comment
     * @return Directive
     */
    public function setComment($comment)
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @param  null|string $name
     * @return Directive
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param  Directive $directive
     * @return Directive
     */
    public function setParent(Directive $directive)
    {
        $this->parent = $directive;

        return $this;
    }

    /**
     * @param  string $value
     * @return Directive
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Convert a string to snake case.
     *
     * @param  string $value
     * @return string
     */
    public static function snakeCase($value)
    {
        if (!ctype_lower($value)) {
            $value = mb_strtolower(preg_replace(['/\s+/u', '/(.)(?=[A-Z])/u'], ['', '$1_'], $value), 'UTF-8');
        }

        return $value;
    }

    /**
     * Convert the configuration to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'name'       => $this->name(),
            'value'      => $this->value(),
            'comment'    => $this->comment(),
            'directives' => $this->children()->toArray(),
        ];
    }

    /**
     * Convert the configuration to JSON.
     *
     * @param  int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convert the configuration to a string.
     *
     * @param  int    $indentSize
     * @param  string $indent
     * @return string
     */
    public function toString($indentSize = 4, $indent = '')
    {
        $config = '';

        $line = $this->name();
        $line = $this->hasValue() ? $line . ' ' . $this->value() : $line;
        $line = !$this->isRoot() && $this->hasChildren() ? $line . ' {' : $line . ';';
        if (!$this->hasName()) {
            $line = rtrim($line, ';');
        }
        $line = $this->hasComment() ? $line . ' # ' . $this->comment() : $line;
        $line = trim($line, ' ');

        $config .= $indent . $line . "\n";

        if (!$this->isRoot()) {
            $indent = self::indent($indent, $indentSize);
        }

        foreach ($this->children() as $directive) {
            $config .= $directive->toString($indentSize, $indent);
        }

        if (!$this->isRoot()) {
            $indent = self::unindent($indent, $indentSize);
        }

        if (!$this->isRoot() && $this->hasChildren()) {
            $config .= $indent . '}' . "\n";
        }

        return $this->isRoot() ? ltrim($config) : $config;
    }

    /**
     * @param  string $indent
     * @param  int    $indentSize
     * @return string
     */
    public static function unindent($indent = '', $indentSize = 4)
    {
        return substr($indent, 0, -$indentSize);
    }

    /**
     * @return null|string
     */
    public function value()
    {
        return $this->value;
    }
}
