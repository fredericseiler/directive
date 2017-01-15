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

    /** @var  Collection */
    public $children;

    /** @var  string|null */
    public $comment;

    /** @var  string|null */
    public $name;

    /** @var  Directive|null */
    public $parent;

    /** @var  string|null */
    public $value;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->children = new Collection;
    }

    /**
     * Get the first child with the specified name.
     *
     * @param  string $name
     * @return Directive
     *
     * @throws ErrorException
     */
    public function __get($name)
    {
        $directive = $this->children($name)->first();

        if ($directive === null) {
            throw new ErrorException("Undefined directive '" . self::snakeCase($name) . "'");
        }

        return $directive;
    }

    /**
     * Convert the directive to a string.
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->name() === null && $this->comment() !== null) {
            $string = '# ' . $this->comment();
        } elseif ($this->hasChildren()) {
            $string = $this->toString();
        } else {
            $string = $this->value();
        }

        return $string;
    }

    /**
     * Get a collection of directives with the same name and parent.
     *
     * @return Collection
     */
    public function all()
    {
        if ($this->hasParent()) {
            $collection = $this->parent()->children($this->name());
        } else {
            $collection = new Collection([$this]);
        }

        return $collection;
    }

    /**
     * Add one or more directives to the stack.
     *
     * @param  Collection|array|Directive|string|null $name
     * @param  string|null                      $value
     * @param  string|null                      $comment
     * @return Directive
     */
    public function attach($name = null, $value = null, $comment = null)
    {
        $collection = $directive = $name;

        if (is_array($collection) || $collection instanceof Collection) {
            foreach ($collection as $directive) {
                $this->attach($directive);
            }

            return $this;
        }

        if (!$directive instanceof Directive) {
            if ($name !== null) {
                $name = self::snakeCase($name);
            }
            
            $directive = new self;
            $directive->setName($name);
            $directive->setValue($value);
            $directive->setComment($comment);
        }

        $directive->parent = $this;

        $this->children->push($directive);

        return $this;
    }

    /**
     * Get the directive's children
     * optionally filtered by name and/or value.
     *
     * @param  string|null $name
     * @param  string|null $value
     * @param  bool        $recursive
     * @return Collection
     */
    public function children($name = null, $value = null, $recursive = false)
    {
        $children = $this->children;

        if ($name !== null) {
            $name = self::snakeCase($name);

            $children = $children->where('name', $name);
        }

        if ($value !== null) {
            $children = $children->where('value', $value);
        }

        if ($recursive === true) {
            foreach ($this->children as $directive) {
                $children = $children->merge($directive->children($name, $value, true));
            }
        }

        return $children->values();
    }

    /**
     * Get the directive's comment.
     *
     * @return mixed
     */
    public function comment()
    {
        return $this->comment;
    }

    /**
     * Remove one or more directives from the stack.
     *
     * @param  Collection|array|Directive|string|null $name
     * @return Directive
     */
    public function detach($name = null)
    {
        $collection = $directive = $name;

        if (is_array($collection) || $collection instanceof Collection) {
            foreach ($collection as $directive) {
                $this->detach($directive);
            }
        }

        if ($directive instanceof Directive) {
            $key = $this->children->search($directive, true);

            if ($key !== false) {
                $this->children->forget($key);

                $directive->parent = null;
            }
        }

        if (is_string($name) && $this->hasChildren($name)) {
            $this->detach($this->children($name));
        }

        if ($directive === null) {
            $this->parent()->detach($this);
        }

        return $this;
    }

    /**
     * Convert a given string to a new directive.
     *
     * @param  string $nginxConfig
     * @return Directive
     */
    public static function fromString($nginxConfig = '')
    {
        $nginxConfig = str_replace(["\r\n", "\n\r", "\r"], "\n", $nginxConfig);
        $nginxConfig = preg_replace('/  +|\t/', ' ', $nginxConfig);

        $instance = new self;

        $instance->builder = new self;

        foreach (explode("\n", $nginxConfig) as $line) {
            $instance->parse(trim($line));
        }

        $instance->setName($instance->builder->name());
        $instance->setValue($instance->builder->value());
        $instance->setComment($instance->builder->comment());
        $instance->attach($instance->builder->children());
        
        $instance->builder = null;

        return $instance;
    }

    /**
     * Determine if the directive has children
     * optionally filtered by name and/or value.
     *
     * @param  string|null $name
     * @param  string|null $value
     * @param  bool        $recursive
     * @return bool
     */
    public function hasChildren($name = null, $value = null, $recursive = false)
    {
        return !$this->children($name, $value, $recursive)->isEmpty();
    }

    /**
     * Determine if the directive has a parent.
     *
     * @return bool
     */
    public function hasParent()
    {
        return $this->parent() !== null;
    }

    /**
     * Add a level of indentation.
     *
     * @param  int    $size
     * @param  string $indentation
     * @return string
     */
    protected static function indent($size = 4, $indentation = '')
    {
        return $indentation . str_repeat(' ', $size);
    }

    /**
     * Get the name of the directive.
     *
     * @return mixed
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Get the parent of the directive.
     *
     * @return Directive
     */
    public function parent()
    {
        return $this->parent;
    }

    /**
     * @param  string $line
     * @return Directive
     */
    protected function parse($line)
    {
        return $this->reformatInlineBlock($line)
            ->searchForOpeningSection($line)
            ->searchForComment($line)
            ->searchForClosingSection($line)
            ->searchForOneLiner($line);
    }

    /**
     * @param  string $line
     * @return Directive
     */
    protected function reformatInlineBlock($line)
    {
        if (preg_match('/^(.*) ?{ ?(.*) ?}( ?# ?(.*))?$/', $line, $matches)) {
            $block = [$matches[1] . '{'];

            $oneLiners = explode(';', $matches[2]);

            foreach ($oneLiners as $oneLiner) {
                $block[] = $oneLiner . ';';
            }

            $comment = array_key_exists(4, $matches) && $matches[4] !== '' ? ' # ' . $matches[4] : null;

            $block[] = '}' . $comment;

            foreach ($block as $blockLine) {
                $this->parse($blockLine);
            }
        }

        return $this;
    }

    /**
     * Get the farthest ancestor of the directive.
     *
     * @return Directive
     */
    public function root()
    {
        return !$this->hasParent() ? $this : $this->parent()->root();
    }

    /**
     * @param  string $line
     * @return Directive
     */
    protected function searchForClosingSection($line)
    {
        if (preg_match('/^}( ?# ?(.*))?$/', $line, $matches)) {
            $this->builder = $this->builder->parent();

            if (array_key_exists(2, $matches) && $matches[2] !== '') {
                $this->builder->attach(null, null, $matches[2]);
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
            $this->builder->attach(null, null, $matches[1]);
        }

        return $this;
    }

    /**
     * @param  string $line
     * @return Directive
     */
    protected function searchForOneLiner($line)
    {
        if (preg_match('/^(\w+) (.+);( ?# ?(.*))?$/', $line, $matches)) {
            $comment = array_key_exists(4, $matches) && $matches[4] !== '' ? $matches[4] : null;

            $this->builder->attach($matches[1], $matches[2], $comment);
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
            $value = array_key_exists(2, $matches) && $matches[2] !== '' ? $matches[2] : null;
            $comment = array_key_exists(4, $matches) && $matches[4] !== '' ? $matches[4] : null;

            $this->builder->attach($matches[1], $value, $comment);

            $this->builder = $this->builder->children()->last();
        }

        return $this;
    }

    /**
     * Set the comment of the directive.
     *
     * @param  mixed $comment
     * @return Directive
     */
    public function setComment($comment = null)
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Set the name of the directive.
     *
     * @param  mixed $name
     * @return Directive
     */
    public function setName($name = null)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the value of the directive.
     *
     * @param  mixed $value
     * @return Directive
     */
    public function setValue($value = null)
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
    protected static function snakeCase($value)
    {
        if (!ctype_lower($value)) {
            $value = mb_strtolower(preg_replace(['/\s+/u', '/(.)(?=[A-Z])/u'], ['', '$1_'], $value), 'UTF-8');
        }

        return $value;
    }

    /**
     * Convert the directive to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'name'     => $this->name(),
            'value'    => $this->value(),
            'comment'  => $this->comment(),
            'children' => $this->children()->toArray(),
        ];
    }

    /**
     * Convert the directive to JSON.
     *
     * @link http://php.net/manual/en/function.json-encode.php
     *
     * @param  int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convert the directive to a string.
     *
     * @param  int    $size
     * @param  string $indentation
     * @return string
     */
    public function toString($size = 4, $indentation = '')
    {
        $config = '';

        $line = $this->name();

        if ($this->value() !== null) {
            $line .= ' ' . $this->value();
        }

        if ($this->hasChildren() && $this->hasParent()) {
            $line .= ' {';
        } elseif ($this->name() !== null) {
            $line .= ';';
        }

        if ($this->comment() !== null) {
            $line .= ' # ' . $this->comment();
        }

        $line = trim($line, ' ');

        $config .= $indentation . $line . "\n";

        if ($this->hasParent()) {
            $indentation = self::indent($size, $indentation);
        }

        foreach ($this->children() as $directive) {
            $config .= $directive->toString($size, $indentation);
        }

        if ($this->hasParent()) {
            $indentation = self::unindent($size, $indentation);
        }

        if ($this->hasChildren() && $this->hasParent()) {
            $config .= $indentation . '}' . "\n";
        }

        return !$this->hasParent() ? ltrim($config) : $config;
    }

    /**
     * Remove a level of indentation.
     *
     * @param  int    $size
     * @param  string $indentation
     * @return string
     */
    protected static function unindent($size = 4, $indentation = '')
    {
        return substr($indentation, 0, -$size);
    }

    /**
     * Get the value of the directive.
     *
     * @return mixed
     */
    public function value()
    {
        return $this->value;
    }
}
