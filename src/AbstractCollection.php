<?php


namespace SimonMarx\Utils;


use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use ReflectionException;
use Traversable;
use ReflectionClass;
use InvalidArgumentException;

/**
 * Class AbstractCollection
 * @package SimonMarx\Utils
 */
abstract class AbstractCollection implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * in some cases you want the functionality of this collection without the type checks
     * in this case simply return in the "getType" method of your class AbstractCollection::TYPELESS_COLLECTION_TYPE
     */
    public const TYPELESS_COLLECTION_TYPE = "SM_TYPELESS_TYPE";

    private array $elements = [];

    private ?ReflectionClass $typeReflection = null;

    /**
     * AbstractCollection constructor.
     * @param iterable $elements
     */
    public function __construct(iterable $elements = [])
    {
        foreach ($elements as $key => $element) {
            $this->set($key, $element);
        }
    }

    /**
     * returns the type the elements should be
     * if you try to add something else in the collection, an exception will be thrown
     *
     * @return string
     */
    abstract public function getType(): string;


    /**
     * @param string|int $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return $this->containsKey($offset);
    }

    /**
     * @param string|int $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param string|int|null $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        if (\is_null($offset)) {
            $this->add($value);
        } else {
            $this->set($offset, $value);
        }
    }

    /**
     * @param string|int $offset
     */
    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return \count($this->elements);
    }

    /**
     * @param mixed $element
     * @return $this
     */
    public function add($element): self
    {
        $this->checkType($element);

        $this->elements[] = $element;
        return $this;
    }

    /**
     * @param string|int $key
     * @param mixed $element
     * @return $this
     */
    public function set($key, $element): self
    {
        $this->checkType($element);

        $this->elements[$key] = $element;
        return $this;
    }

    /**
     * @param string|int $key
     * @return bool
     */
    public function containsKey($key): bool
    {
        return \array_key_exists($key, $this->elements);
    }

    /**
     * @param string|int $key
     * @return mixed|null
     */
    public function remove($key)
    {
        if (!$this->containsKey($key)) {
            return null;
        }

        if (!\array_key_exists($key, $this->elements)) {
            return null;
        }

        $removed = $this->elements[$key];

        unset($this->elements[$key]);

        return $removed;
    }

    /**
     * @param mixed $element
     * @return bool
     */
    public function removeElement($element): bool
    {
        $key = \array_search($element, $this->elements, true);

        if ($key === false) {
            return false;
        }

        $this->remove($key);

        return true;
    }

    /**
     * @param string|int $key
     * @return mixed|null
     */
    public function get($key)
    {
        return $this->elements[$key] ?? null;
    }

    /**
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->elements);
    }

    /**
     * @param AbstractCollection $collection
     * @return $this
     */
    public function merge(AbstractCollection $collection): self
    {
        $this->compareCollectionTypes($collection);

        foreach ($collection as $item) {
            $this->add($item);
        }

        return $this;
    }

    /**
     * @param AbstractCollection $collection
     * @return $this
     */
    public function replace(AbstractCollection $collection): self
    {
        $this->compareCollectionTypes($collection);

        foreach ($collection as $key => $element) {
            if (\is_int($key)) {
                $this->add($element);
            } else {
                $this->set($key, $element);
            }
        }

        return $this;
    }

    /**
     * @param AbstractCollection $collection
     * @throws InvalidArgumentException
     */
    private function compareCollectionTypes(AbstractCollection $collection): void
    {
        if ($this->getType() === self::TYPELESS_COLLECTION_TYPE) {
            return;
        }

        if ($collection->isObjectType() && $this->isObjectType()) {
            try {
                $rc = new ReflectionClass($collection->getType());

                if (
                    ($this->typeIsInterface() && $rc->implementsInterface($this->getType()))
                    || ($collection->typeIsInterface() && $this->getTypeReflection()->implementsInterface($collection->getType()))
                ) {
                    return;
                }

                if ($rc->isSubclassOf($this->getType()) || $rc->getName() === $this->getType()) {
                    return;
                }
            } catch (ReflectionException $exception) {
                // should not happen
            }
        }

        if ($collection->getType() === $this->getType()) {
            return;
        }

        throw new InvalidArgumentException(sprintf('The collection "%s" must have elements which are instances of "%s" to be merged with collection of type "%s", but elements of type "%s" given', \get_class($collection), $this->getType(), \get_class($this), $collection->getType()));
    }

    /**
     * @return bool
     */
    public function isObjectType(): bool
    {
        return \class_exists($this->getType()) || interface_exists($this->getType());
    }

    /**
     * @param mixed $element
     *
     * @return void
     */
    private function checkType($element)
    {
        if ($this->getType() === self::TYPELESS_COLLECTION_TYPE) {
            return;
        }

        $exist = false;
        $type = \gettype($element);

        try {
            if (\is_object($element)) {
                $rc = new ReflectionClass($element);

                $type = $rc->getName();

                $exist = $rc->isSubclassOf($this->getType()) || $rc->getName() === $this->getType();
            } else {
                $fnc = 'is_' . $this->getType();

                if (\function_exists($fnc) && \is_callable($fnc)) {
                    $exist = $fnc($element);
                }
            }
        } catch (ReflectionException $exception) {
            // should not happen, cause we check if the element is an object
        }

        if ($exist !== true) {
            $this->throwInvalidElementException($type);
        }
    }

    /**
     * @param string $elementType
     */
    private function throwInvalidElementException(string $elementType): void
    {
        throw new InvalidArgumentException(sprintf('Collection of type "%s" expect elements of type "%s" but element with type "%s" given', \get_class($this), $this->getType(), $elementType));
    }

    /**
     * @param Closure $closure
     * @return $this
     */
    public function filter(Closure $closure): self
    {
        return new $this(array_filter($this->elements, $closure, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * @param Closure $closure
     * @return $this
     */
    public function sort(Closure $closure): self
    {
        usort($this->elements, $closure);
        return $this;
    }

    /**
     * @param Closure $closure
     * @return array
     */
    public function map(Closure $closure): array
    {
        return \array_map($closure, $this->elements);
    }

    /**
     * @return mixed
     */
    public function first()
    {
        return reset($this->elements);
    }

    /**
     * @param Closure $closure
     * @return mixed
     */
    public function findFirst(Closure $closure)
    {
        return $this->filter($closure)->first();
    }

    /**
     * @return ReflectionClass
     * @throws ReflectionException
     */
    private function getSelfReflection(): ReflectionClass
    {
        return new ReflectionClass($this);
    }

    /**
     * @return ReflectionClass|null
     * @throws ReflectionException
     */
    private function getTypeReflection(): ?ReflectionClass
    {
        if ($this->isObjectType() && !$this->typeReflection) {
            $this->typeReflection = new ReflectionClass($this->getType());
        }

        return $this->typeReflection;
    }

    /**
     * @param mixed $element
     * @return bool
     */
    public function contains($element)
    {
        return in_array($element, $this->elements, true);
    }

    /**
     * @return array
     */
    public function toPlainArray(): array
    {
        return $this->elements;
    }

    /**
     * @return bool
     * @throws ReflectionException
     */
    public function typeIsInterface(): bool
    {
        if ($this->getTypeReflection() === null) {
            return false;
        }

        return $this->getTypeReflection()->isInterface();
    }
}
