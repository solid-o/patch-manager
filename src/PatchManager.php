<?php

declare(strict_types=1);

namespace Solido\PatchManager;

use JsonSchema\Validator;
use Psr\Cache\CacheItemPoolInterface;
use Solido\Common\AdapterFactory;
use Solido\Common\AdapterFactoryInterface;
use Solido\DataMapper\Exception\MappingErrorException;
use Solido\DataTransformers\Exception\TransformationFailedException;
use Solido\PatchManager\Exception\Error;
use Solido\PatchManager\Exception\InvalidJSONException;
use Solido\PatchManager\Exception\OperationNotAllowedException;
use Solido\PatchManager\Exception\UnmergeablePatchException;
use Solido\PatchManager\JSONPointer\Path;
use stdClass;
use Symfony\Component\Form\Exception\TransformationFailedException as FormTransformationFailedException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function array_unique;
use function assert;
use function class_exists;
use function count;
use function in_array;
use function json_decode;
use function json_encode;
use function Safe\file_get_contents;
use function Safe\preg_match;
use function Safe\realpath;

use const JSON_THROW_ON_ERROR;

class PatchManager implements PatchManagerInterface
{
    private AdapterFactoryInterface $adapterFactory;
    private ValidatorInterface $validator;
    private OperationFactory $operationsFactory;
    protected ?CacheItemPoolInterface $cache;

    public function __construct(?ValidatorInterface $validator = null)
    {
        if ($validator === null) {
            if (! class_exists(Validation::class)) {
                throw new Error('Symfony validator component is not installed. Run composer require symfony/validator to install it.');
            }

            $validator = Validation::createValidator();
        }

        $this->adapterFactory = new AdapterFactory();
        $this->validator = $validator;
        $this->operationsFactory = new OperationFactory();
    }

    public function setAdapterFactory(AdapterFactoryInterface $adapterFactory): void
    {
        $this->adapterFactory = $adapterFactory;
    }

    public function patch(PatchableInterface $patchable, object $request): void
    {
        $adapter = $this->adapterFactory->createRequestAdapter($request);
        if (preg_match('#^application/merge-patch\\+#i', $adapter->getContentType())) {
            if (! $patchable instanceof MergePatchableInterface) {
                throw new UnmergeablePatchException('Resource cannot be merge patched.');
            }

            $this->mergePatch($patchable, $request);

            return;
        }

        $object = (array) Validator::arrayToObjectRecursive($adapter->getRequestParams());

        $validator = new Validator();
        $validator->validate($object, $this->getSchema());

        if (! $validator->isValid()) {
            throw new InvalidJSONException('Invalid document.');
        }

        $factory = $this->getOperationsFactory();

        foreach ($object as $operation) {
            if (isset($operation->value)) {
                $operation->value = json_decode(json_encode($operation->value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            }

            $op = $factory->factory($operation->op);

            try {
                $op->execute($patchable, $operation);
            } catch (
                OperationNotAllowedException |
                NoSuchPropertyException |
                UnexpectedTypeException |
                FormTransformationFailedException |
                TransformationFailedException $exception
            ) {
                throw new InvalidJSONException('Operation failed at path "' . $operation->path . '"', 0, $exception);
            }
        }

        $this->validate($object, $patchable);
        $this->commit($patchable);
    }

    /**
     * Sets the cache pool.
     * Used to store parsed validator schema, for example.
     *
     * @required
     */
    public function setCache(?CacheItemPoolInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Gets the validation schema.
     */
    protected function getSchema(): object
    {
        if ($this->cache !== null) {
            $item = $this->cache->getItem('patch_manager_schema');
            if ($item->isHit()) {
                // @phpstan-ignore-next-line
                return $item->get();
            }
        }

        $schema = json_decode(file_get_contents(realpath(__DIR__ . '/data/schema.json')), false, 512, JSON_THROW_ON_ERROR);
        assert($schema instanceof stdClass);

        if (isset($item)) {
            assert($this->cache !== null);

            $item->set($schema);
            $this->cache->saveDeferred($item);
        }

        return $schema;
    }

    /**
     * Gets an instance of OperationFactory.
     */
    protected function getOperationsFactory(): OperationFactory
    {
        return $this->operationsFactory;
    }

    /**
     * Executes a merge-PATCH.
     *
     * @throws MappingErrorException
     */
    protected function mergePatch(MergePatchableInterface $patchable, object $request): void
    {
        $mapper = $patchable->getDataMapper();
        $mapper->map($request);
        $this->commit($patchable);
    }

    /**
     * Calls the validator service and throws an InvalidJSONException
     * if the object is invalid.
     *
     * @param stdClass[] $operations
     *
     * @throws InvalidJSONException
     */
    protected function validate(array $operations, PatchableInterface $patchable): void
    {
        $violations = $this->validator->validate($patchable);
        if (count($violations) === 0) {
            return;
        }

        $paths = [];
        foreach ($operations as $operation) {
            $path = new Path($operation->path);
            $paths[] = $path->getElement(0);
        }

        $paths = array_unique($paths);
        foreach ($violations as $i => $violation) {
            $path = $violation->getPropertyPath();
            if (! $path) {
                continue;
            }

            $path = new PropertyPath($path);
            if (in_array($path->getElement(0), $paths, true)) {
                continue;
            }

            $violations->remove($i);
        }

        if ($violations->count() === 0) {
            return;
        }

        throw new InvalidJSONException('Invalid entity');
    }

    /**
     * Commit modifications.
     */
    protected function commit(PatchableInterface $patchable): void
    {
        $patchable->commit();
    }
}
