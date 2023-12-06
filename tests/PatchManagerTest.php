<?php declare(strict_types=1);

namespace Solido\PatchManager\Tests;

use Nyholm\Psr7\ServerRequest;
use Prophecy\PhpUnit\ProphecyTrait;
use Solido\DataMapper\DataMapperInterface;
use Solido\DataMapper\Exception\MappingErrorException;
use Solido\DataMapper\MappingResultInterface;
use Solido\PatchManager\Exception\InvalidJSONException;
use Solido\PatchManager\Exception\OperationNotAllowedException;
use Solido\PatchManager\Exception\UnmergeablePatchException;
use Solido\PatchManager\MergePatchableInterface;
use Solido\PatchManager\PatchableInterface;
use Solido\PatchManager\PatchManager;
use Solido\PatchManager\PatchManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PatchManagerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ValidatorInterface|ObjectProphecy
     */
    private ObjectProphecy $validator;

    private PatchManager $patchManager;
    private static CacheItemPoolInterface $cache;

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        self::$cache = new ArrayAdapter();
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->validator = $this->prophesize(ValidatorInterface::class);
        $this->validator->validate(Argument::any())->willReturn(new ConstraintViolationList());

        $this->patchManager = $this->createPatchManager();
    }

    public function testPatchManagerCouldBeCreatedWithoutArguments(): void
    {
        $this->expectNotToPerformAssertions();

        new PatchManager();
    }

    public function testPatchShouldRaiseAnErrorIfNotImplementingPatchInterface(): void
    {
        $this->expectException(\TypeError::class);
        $this->patchManager->patch(new \stdClass(), $this->prophesize(Request::class)->reveal());
    }

    public function provideMergePatchContentType(): iterable
    {
        yield [ 'application/merge-patch+json' ];
        yield [ 'application/merge-patch+xml' ];
        yield [ 'application/merge-patch+x-www-form-urlencoded' ];
    }

    /**
     * @dataProvider provideMergePatchContentType
     */
    public function testPatchShouldOperateMergePatchIfContentTypeIsCorrect(string $contentType): void
    {
        $request = $this->prophesize(Request::class);
        $request->reveal()->headers = new HeaderBag([
            'content-type' => $contentType,
        ]);

        $patchable = $this->prophesize(MergePatchableInterface::class);
        $patchable->getDataMapper()->willReturn($mapper = $this->prophesize(DataMapperInterface::class));
        $patchable->commit()->shouldBeCalled();

        $mapper->map($request)->shouldBeCalled();
        $this->patchManager->patch($patchable->reveal(), $request->reveal());
    }

    public function testMergePatchShouldThrowIfDataIsNotValid(): void
    {
        $this->expectException(MappingErrorException::class);
        $request = $this->prophesize(Request::class);
        $request->reveal()->headers = new HeaderBag([
            'content-type' => 'application/merge-patch+json',
        ]);

        $patchable = $this->prophesize(MergePatchableInterface::class);
        $patchable->getDataMapper()->willReturn($mapper = $this->prophesize(DataMapperInterface::class));
        $patchable->commit()->shouldNotBeCalled();

        $mapper->map($request)->willThrow(new MappingErrorException(
            new class implements MappingResultInterface {
                public function getName(): string
                {
                    return '';
                }

                public function getChildren(): array
                {
                    return [];
                }

                public function getErrors(): array
                {
                    return ['err'];
                }
            }
        ));

        $this->patchManager->patch($patchable->reveal(), $request->reveal());
    }

    public function getInvalidJson(): iterable
    {
        yield [[]];
        yield [[
            ['op' => 'test', 'value' => 'foo'],
        ]];
    }

    /**
     * @dataProvider getInvalidJson
     */
    public function testPatchShouldThrowIfDocumentIsInvalid(array $params): void
    {
        $this->expectException(InvalidJSONException::class);
        $this->expectExceptionMessage('Invalid document.');
        $request = $this->prophesize(Request::class);
        $request->reveal()->headers = new HeaderBag();
        $request->reveal()->request = new InputBag($params);

        $patchable = $this->prophesize(PatchableInterface::class);
        $patchable->commit()->shouldNotBeCalled();

        $this->patchManager->patch($patchable->reveal(), $request->reveal());
    }

    public function getInvalidJsonAndObject(): iterable
    {
        yield [
            [
                ['op' => 'test', 'path' => '/a', 'value' => 'foo'],
            ],
            new class() implements PatchableInterface {
                public $b;

                public function commit(): void
                {
                }
            },
        ];

        yield [
            [
                ['op' => 'test', 'path' => '/a/b', 'value' => 'foo'],
            ],
            new class() implements PatchableInterface {
                public $a = 'foobar';

                public function commit(): void
                {
                }
            },
        ];
    }

    /**
     * @dataProvider getInvalidJsonAndObject
     */
    public function testPatchShouldThrowIfOperationErrored(array $params, $object): void
    {
        $this->expectException(InvalidJSONException::class);
        $this->expectExceptionMessageMatches('/Operation failed at path/');
        $request = $this->prophesize(Request::class);
        $request->reveal()->headers = new HeaderBag();
        $request->reveal()->request = new InputBag($params);

        $this->patchManager->patch($object, $request->reveal());
    }

    public function testPatchShouldCommitModifications(): void
    {
        $object = $this->prophesize(PatchableInterface::class);
        $object->commit()->shouldBeCalled();

        $object->reveal()->a = ['b' => ['c' => 'foo']];

        $params = [
            ['op' => 'test', 'path' => '/a/b/c', 'value' => 'foo'],
            ['op' => 'remove', 'path' => '/a/b/c'],
            ['op' => 'add', 'path' => '/a/b/c', 'value' => ['foo', 'bar']],
            ['op' => 'add', 'path' => '/a/b/b', 'value' => ['fooz', 'barz']],
            ['op' => 'replace', 'path' => '/a/b/c', 'value' => 42],
            ['op' => 'move', 'from' => '/a/b/c', 'path' => '/a/b/d'],
            ['op' => 'copy', 'from' => '/a/b/d', 'path' => '/a/b/e'],
        ];

        $request = $this->prophesize(Request::class);
        $request->reveal()->headers = new HeaderBag();
        $request->reveal()->request = new InputBag($params);

        $this->patchManager->patch($object->reveal(), $request->reveal());

        self::assertSame([
            'b' => [
                'b' => ['fooz', 'barz'],
                'd' => 42,
                'e' => 42,
            ],
        ], $object->reveal()->a);
    }

    public function testPatchShouldSupportPsr7ServerRequest(): void
    {
        $object = $this->prophesize(PatchableInterface::class);
        $object->commit()->shouldBeCalled();

        $object->reveal()->a = ['b' => ['c' => 'foo']];

        $params = [
            ['op' => 'test', 'path' => '/a/b/c', 'value' => 'foo'],
            ['op' => 'remove', 'path' => '/a/b/c'],
            ['op' => 'add', 'path' => '/a/b/c', 'value' => ['foo', 'bar']],
            ['op' => 'add', 'path' => '/a/b/b', 'value' => ['fooz', 'barz']],
            ['op' => 'replace', 'path' => '/a/b/c', 'value' => 42],
            ['op' => 'move', 'from' => '/a/b/c', 'path' => '/a/b/d'],
            ['op' => 'copy', 'from' => '/a/b/d', 'path' => '/a/b/e'],
        ];

        $request = (new ServerRequest('PATCH', '/'))->withParsedBody($params);
        $this->patchManager->patch($object->reveal(), $request);

        self::assertSame([
            'b' => [
                'b' => ['fooz', 'barz'],
                'd' => 42,
                'e' => 42,
            ],
        ], $object->reveal()->a);
    }

    public function testPatchShouldThrowInvalidJSONExceptionIfObjectIsInvalid(): void
    {
        $this->expectException(InvalidJSONException::class);
        $this->expectExceptionMessageMatches('/Invalid entity/');
        $object = $this->prophesize(PatchableInterface::class);
        $object->a = ['b' => ['c' => 'foo']];

        $this->validator->validate($object)->willReturn(new ConstraintViolationList([
            new ConstraintViolation('Invalid', 'Invalid', ['a'], '', 'non-patched-property', 'invalid'),
            new ConstraintViolation('Invalid', 'Invalid', ['a'], '', 'property', 'invalid'),
            new ConstraintViolation('Invalid', 'Invalid', ['a'], '', 'a[b]', 'invalid'),
        ]));

        $request = $this->prophesize(Request::class);
        $request->reveal()->headers = new HeaderBag();
        $request->reveal()->request = new InputBag([
            ['op' => 'test', 'path' => '/a/b/c', 'value' => 'foo'],
        ]);

        $this->patchManager->patch($object->reveal(), $request->reveal());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testPatchShouldNotThrowOnObjectInvalidForNonPatchedProperty(): void
    {
        $object = $this->prophesize(PatchableInterface::class);
        $object->a = ['b' => ['c' => 'foo']];

        $this->validator->validate($object)->willReturn(new ConstraintViolationList([
            new ConstraintViolation('Invalid', 'Invalid', ['a'], '', 'non-patched-property', 'invalid'),
            new ConstraintViolation('Invalid', 'Invalid', ['a'], '', 'property', 'invalid'),
        ]));

        $request = $this->prophesize(Request::class);
        $request->reveal()->headers = new HeaderBag();
        $request->reveal()->request = new InputBag([
            ['op' => 'test', 'path' => '/a/b/c', 'value' => 'foo'],
        ]);

        $this->patchManager->patch($object->reveal(), $request->reveal());
    }

    public function testPatchShouldNotIgnoreRootErrors(): void
    {
        $this->expectException(InvalidJSONException::class);
        $this->expectExceptionMessageMatches('/Invalid entity/');
        $object = $this->prophesize(PatchableInterface::class);
        $object->a = ['b' => ['c' => 'foo']];

        $this->validator->validate($object)->willReturn(new ConstraintViolationList([
            new ConstraintViolation('Invalid', 'Invalid', ['a'], '', null, 'invalid'),
            new ConstraintViolation('Invalid', 'Invalid', ['a'], '', 'property', 'invalid'),
        ]));

        $request = $this->prophesize(Request::class);
        $request->reveal()->headers = new HeaderBag();
        $request->reveal()->request = new InputBag([
            ['op' => 'test', 'path' => '/a/b/c', 'value' => 'foo'],
        ]);

        $this->patchManager->patch($object->reveal(), $request->reveal());
    }

    public function testPatchShouldThrowInvalidJSONExceptionOnOperationNotAllowedException(): void
    {
        $this->expectException(InvalidJSONException::class);
        $this->expectExceptionMessageMatches('/Operation failed at path /');
        $params = [
            [
                'op' => 'remove',
                'path' => '/items/0',
            ],
        ];

        $object = new class() implements PatchableInterface {
            private $items;

            public function __construct()
            {
                $this->items = ['this-is-an-item'];
            }

            public function getItems(): array
            {
                return $this->items;
            }

            public function addItem($item): void
            {
                $this->items[] = $item;
            }

            public function removeItem($item): void
            {
                throw new OperationNotAllowedException();
            }

            public function commit(): void
            {
            }
        };

        $request = $this->prophesize(Request::class);
        $request->reveal()->headers = new HeaderBag();
        $request->reveal()->request = new InputBag($params);

        $this->patchManager->patch($object, $request->reveal());
    }

    public function testPatchShouldThrowIfObjectIsNotInstanceOfMergeablePatchableInterface(): void
    {
        $this->expectException(UnmergeablePatchException::class);
        $this->expectExceptionMessage('Resource cannot be merge patched.');
        $object = $this->prophesize(PatchableInterface::class);
        $request = $this->prophesize(Request::class);

        $request->reveal()->headers = new HeaderBag([
            'content-type' => 'application/merge-patch+json',
        ]);

        $this->patchManager->patch($object->reveal(), $request->reveal());
    }

    protected function createPatchManager(): PatchManagerInterface
    {
        $manager = new PatchManager($this->validator->reveal());
        $manager->setCache(self::$cache);

        return $manager;
    }
}
