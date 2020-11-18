<?php

declare(strict_types=1);

namespace Solido\PatchManager;

use JsonSchema\Validator;
use Psr\Cache\CacheItemPoolInterface;
use Solido\Common\Form\AutoSubmitRequestHandler;
use Solido\PatchManager\Exception\Error;
use Solido\PatchManager\Exception\FormInvalidException;
use Solido\PatchManager\Exception\FormNotSubmittedException;
use Solido\PatchManager\Exception\InvalidJSONException;
use Solido\PatchManager\Exception\OperationNotAllowedException;
use Solido\PatchManager\Exception\UnmergeablePatchException;
use Solido\PatchManager\JSONPointer\Path;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
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
    private FormFactoryInterface $formFactory;
    private ValidatorInterface $validator;
    private OperationFactory $operationsFactory;
    protected ?CacheItemPoolInterface $cache;

    public function __construct(?FormFactoryInterface $formFactory = null, ?ValidatorInterface $validator = null)
    {
        if ($formFactory === null) {
            if (! class_exists(Forms::class)) {
                throw new Error('Symfony form component is not installed. Run composer require symfony/form to install it.');
            }

            $formFactory = Forms::createFormFactory();
        }

        if ($validator === null) {
            if (! class_exists(Validation::class)) {
                throw new Error('Symfony validator component is not installed. Run composer require symfony/validator to install it.');
            }

            $validator = Validation::createValidator();
        }

        $this->formFactory = $formFactory;
        $this->validator = $validator;
        $this->operationsFactory = new OperationFactory();
    }

    public function patch(PatchableInterface $patchable, Request $request): void
    {
        if (preg_match('#^application/merge-patch\\+#i', (string) $request->headers->get('Content-Type', ''))) {
            if (! $patchable instanceof MergeablePatchableInterface) {
                throw new UnmergeablePatchException('Resource cannot be merge patched.');
            }

            $this->mergePatch($patchable, $request);

            return;
        }

        $object = (array) Validator::arrayToObjectRecursive($request->request->all());

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
            } catch (OperationNotAllowedException | NoSuchPropertyException | UnexpectedTypeException | TransformationFailedException $exception) {
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
     *
     * @return object
     */
    protected function getSchema()
    {
        if ($this->cache !== null) {
            $item = $this->cache->getItem('patch_manager_schema');
            if ($item->isHit()) {
                return $item->get();
            }
        }

        $schema = json_decode(file_get_contents(realpath(__DIR__ . '/data/schema.json')), true, 512, JSON_THROW_ON_ERROR);

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
     * @throws FormInvalidException
     * @throws FormNotSubmittedException
     */
    protected function mergePatch(MergeablePatchableInterface $patchable, Request $request): void
    {
        $builder = $this->formFactory->createNamedBuilder('', $patchable->getTypeClass(), $patchable, [
            'method' => Request::METHOD_PATCH,
        ]);

        $builder->setRequestHandler(new AutoSubmitRequestHandler());

        $form = $builder->getForm();
        $form->handleRequest($request);
        if (! $form->isSubmitted()) {
            throw new FormNotSubmittedException($form);
        }

        if (! $form->isValid()) {
            throw new FormInvalidException($form);
        }

        $this->commit($patchable);
    }

    /**
     * Calls the validator service and throws an InvalidJSONException
     * if the object is invalid.
     *
     * @param mixed[] $operations
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
