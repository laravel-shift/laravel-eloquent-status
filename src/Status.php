<?php

namespace Makeable\EloquentStatus;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use JsonSerializable;

abstract class Status implements Arrayable, JsonSerializable
{
    /**
     * @var string
     */
    protected $value;

    /**
     * @param $value
     * @param bool $validate
     * @throws InvalidStatusException
     */
    public function __construct($value, bool $validate = true)
    {
        $value = snake_case($value);

        if ($validate && ! static::validate($value)) {
            throw new InvalidStatusException("Invalid status {$value}");
        }

        $this->value = $value;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->get();
    }

    /**
     * @param $value
     * @return Status|null
     */
    public static function find($value)
    {
        try {
            return new static($value);
        } catch (InvalidStatusException $e) {
            return;
        }
    }

    /**
     * @return string
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @return string
     */
    public function toArray()
    {
        return $this->get();
    }

    /**
     * @return Collection
     */
    public static function all()
    {
        $class = new ReflectionClass(static::class);

        return collect($class->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(function (ReflectionMethod $method) {
                return $method->getDeclaringClass()->getName() === static::class;
            })
            ->reject(function (ReflectionMethod $method) {
                return $method->isStatic();
            })
            ->map(function (ReflectionMethod $method) {
                return new static($method->getName(), false);
            });
    }

    /**
     * @param $model
     * @return Status
     * @throws Exception
     */
    public static function guess($model)
    {
        if (! method_exists($model, 'checkStatus')) {
            throw new Exception(class_basename($model).' must implement a checkStatus() method');
        }

        return static::all()->first(function ($status) use ($model) {
            return $model->checkStatus($status);
        });
    }

    /**
     * @param $value
     * @return bool
     */
    public static function validate($value)
    {
        return static::all()->contains($value);
    }

    /**
     * @return string
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return str_replace('_', ' ', title_case($this->value));
    }

    /**
     * @param $query
     * @return Builder
     */
    public function scope($query)
    {
        $method = camel_case($this->get());

        return $this->$method($query);
    }
}
