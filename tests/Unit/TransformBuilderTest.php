<?php

namespace Flugg\Responder\Tests\Unit;

use Flugg\Responder\Contracts\TransformFactory;
use Flugg\Responder\Exceptions\InvalidSerializerException;
use Flugg\Responder\Exceptions\InvalidSuccessSerializerException;
use Flugg\Responder\Pagination\CursorPaginator;
use Flugg\Responder\Pagination\PaginatorFactory;
use Flugg\Responder\Resources\ResourceFactory;
use Flugg\Responder\Serializers\SuccessSerializer;
use Flugg\Responder\Tests\TestCase;
use Flugg\Responder\TransformBuilder;
use Flugg\Responder\Transformers\Transformer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use League\Fractal\Pagination\Cursor;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Serializer\JsonApiSerializer;
use Mockery;
use stdClass;

/**
 * Unit tests for the [Flugg\Responder\TransformBuilderTest] class.
 *
 * @package flugger/laravel-responder
 * @author  Alexander Tømmerås <flugged@gmail.com>
 * @license The MIT License
 */
class TransformBuilderTest extends TestCase
{
    /**
     * A mock of a [ResourceFactory] class.
     *
     * @var \Mockery\MockInterface
     */
    protected $resourceFactory;

    /**
     * A mock of a [TransformFactory] class.
     *
     * @var \Mockery\MockInterface
     */
    protected $transformFactory;

    /**
     * A mock of a [PaginatorFactory] class.
     *
     * @var \Mockery\MockInterface
     */
    protected $paginatorFactory;

    /**
     * A mock of a [ResourceInterface] class.
     *
     * @var \Mockery\MockInterface
     */
    protected $resource;

    /**
     * A mock of a [SerializerAbstract] class.
     *
     * @var \Mockery\MockInterface
     */
    protected $serializer;

    /**
     * The [TransformBuilder] class being tested.
     *
     * @var \Flugg\Responder\TransformBuilder
     */
    protected $builder;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->resourceFactory = Mockery::mock(ResourceFactory::class);
        $this->transformFactory = Mockery::mock(TransformFactory::class);
        $this->paginatorFactory = Mockery::mock(PaginatorFactory::class);
        $this->resourceFactory->shouldReceive('make')->andReturn($this->resource = $this->mockResource());
        $this->builder = new TransformBuilder($this->resourceFactory, $this->transformFactory, $this->paginatorFactory);
        $this->builder->serializer($this->serializer = Mockery::mock(SuccessSerializer::class));
    }

    /**
     * Assert that the [resource] method uses the [ResourceFactory] to create resources.
     */
    public function testResourceMethodUsesResourceFactory()
    {
        $result = $this->builder->resource($data = ['foo' => 1], $transformer = $this->mockTransformer(), $resourceKey = 'foo');

        $this->assertSame($this->builder, $result);
        $this->resourceFactory->shouldHaveReceived('make')->with($data, $transformer, $resourceKey)->once();
    }

    /**
     * Assert that the [resource] method sets cursor on the resource if data is an instance
     * of [CursorPaginator].
     */
    public function testResourceMethodSetsCursorOnResource()
    {
        $cursor = Mockery::mock(Cursor::class);
        $this->paginatorFactory->shouldReceive('makeCursor')->andReturn($cursor);

        $this->builder->resource($data = Mockery::mock(CursorPaginator::class));

        $this->resource->shouldHaveReceived('setCursor')->with($cursor)->once();
    }

    /**
     * Assert that the [resource] method sets paginator on the resource if data is an instance
     * of [LengthAwarePaginator].
     */
    public function testResourceMethodSetsPagintorOnResource()
    {
        $paginator = Mockery::mock(IlluminatePaginatorAdapter::class);
        $this->paginatorFactory->shouldReceive('make')->andReturn($paginator);

        $this->builder->resource($data = Mockery::mock(LengthAwarePaginator::class));

        $this->resource->shouldHaveReceived('setPaginator')->with($paginator)->once();
    }

    /**
     * Assert that the [cursor] method allows manually setting cursor on resource.
     */
    public function testCursorMethodSetsCursorsOnResource()
    {
        $cursor = Mockery::mock(Cursor::class);
        $this->paginatorFactory->shouldReceive('makeCursor')->andReturn($cursor);

        $this->builder->resource()->cursor($cursor);

        $this->resource->shouldHaveReceived('setCursor')->with($cursor)->once();
    }

    /**
     * Assert that the [paginator] method allows manually setting paginator on resource.
     */
    public function testPaginatorMethodSetsPaginatorsOnResource()
    {
        $paginator = Mockery::mock(IlluminatePaginatorAdapter::class);
        $this->paginatorFactory->shouldReceive('make')->andReturn($paginator);

        $this->builder->resource()->paginator($paginator);

        $this->resource->shouldHaveReceived('setPaginator')->with($paginator)->once();
    }

    /**
     * Assert that the [meta] method adds meta data to the resource.
     */
    public function testMetaMethodAddsMetaDataToResource()
    {
        $result = $this->builder->resource()->meta($meta = ['foo' => 1]);

        $this->assertSame($this->builder, $result);
        $this->resource->shouldHaveReceived('setMeta')->with($meta)->once();
    }

    /**
     * Assert that the [transform] method transforms data using [TransformFactory].
     */
    public function testTransformMethodUsesTransformFactoryToTransformData()
    {
        $this->transformFactory->shouldReceive('make')->andReturn($data = ['foo' => 123]);

        $result = $this->builder->resource()->transform();

        $this->assertEquals($data, $result);
        $this->transformFactory->shouldHaveReceived('make')->with($this->resource, $this->serializer, [
            'includes' => [],
            'excludes' => [],
            'fieldsets' => [],
        ])->once();
    }

    /**
     * Assert that the [serializer] method sets the serializer that is sent to the
     * [TransformFactory].
     */
    public function testSerializerMethodSetsSerializerSentToTransformFactory()
    {
        $this->transformFactory->shouldReceive('make')->andReturn([]);

        $this->builder->resource()->serializer($serializer = new JsonApiSerializer)->transform();

        $this->transformFactory->shouldHaveReceived('make')->with($this->resource, $serializer, [
            'includes' => [],
            'excludes' => [],
            'fieldsets' => [],
        ])->once();
    }

    /**
     * Assert that the [serializer] method allows class name strings.
     */
    public function testSerializerMethodAllowsClassNameStrings()
    {
        $this->transformFactory->shouldReceive('make')->andReturn([]);

        $this->builder->resource()->serializer($serializer = JsonApiSerializer::class)->transform();

        $this->transformFactory->shouldHaveReceived('make')->with($this->resource, $serializer, [
            'includes' => [],
            'excludes' => [],
            'fieldsets' => [],
        ])->once();
    }

    /**
     * Assert that the [serializer] method throws [InvalidSuccessSerializerException] exception when
     * given an invalid serializer.
     */
    public function testSerializerMethodThrowsExceptionWhenGivenInvalidSerializer()
    {
        $this->expectException(InvalidSuccessSerializerException::class);

        $this->builder->serializer($serializer = stdClass::class);
    }

    /**
     * Assert that the [with] method sets the included relationships that are sent to the
     * [TransformFactory].
     */
    public function testWithMethodSetsIncludedRelationsSentToFactory()
    {
        $this->transformFactory->shouldReceive('make')->andReturn([]);

        $this->builder->resource()->with($relations = ['foo', 'bar'])->transform();

        $this->transformFactory->shouldHaveReceived('make')->with($this->resource, $this->serializer, [
            'includes' => $relations,
            'excludes' => [],
            'fieldsets' => [],
        ])->once();
    }

    /**
     * Assert that the [with] method allows to be called multiple times and accepts strings
     * as parameters.
     */
    public function testWithMethodAllowsMultipleCallsAndStrings()
    {
        $this->transformFactory->shouldReceive('make')->andReturn([]);

        $this->builder->resource()->with('foo')->with('bar', 'baz')->transform();

        $this->transformFactory->shouldHaveReceived('make')->with($this->resource, $this->serializer, [
            'includes' => ['foo', 'bar', 'baz'],
            'excludes' => [],
            'fieldsets' => [],
        ])->once();
    }

    /**
     * Assert that the [without] method sets the excluded relationships that are sent to the
     * [TransformFactory].
     */
    public function testWithoutMethodSetsExcludedRelationsSentToFactory()
    {
        $this->transformFactory->shouldReceive('make')->andReturn([]);

        $this->builder->resource()->without($relations = ['foo', 'bar'])->transform();

        $this->transformFactory->shouldHaveReceived('make')->with($this->resource, $this->serializer, [
            'includes' => [],
            'excludes' => $relations,
            'fieldsets' => [],
        ])->once();
    }

    /**
     * Assert that the [with] method allows to be called multiple times and accepts strings
     * as parameters.
     */
    public function testWithoutMethodAllowsMultipleCallsAndStrings()
    {
        $this->transformFactory->shouldReceive('make')->andReturn([]);

        $this->builder->resource()->without('foo')->without('bar', 'baz')->transform();

        $this->transformFactory->shouldHaveReceived('make')->with($this->resource, $this->serializer, [
            'includes' => [],
            'excludes' => ['foo', 'bar', 'baz'],
            'fieldsets' => [],
        ])->once();
    }

    /**
     * Assert that the [transform] method extracts default relationships from transformer and
     * automatically eager loads all relationships.
     */
    public function testTransformMethodExtractsAndEagerLoadsRelations()
    {
        $this->transformFactory->shouldReceive('make')->andReturn([]);
        $this->resource->shouldReceive('getData')->andReturn($model = Mockery::mock(Model::class));
        $model->shouldReceive('load')->andReturnSelf();
        $this->resource->shouldReceive('getTransformer')->andReturn($transformer = Mockery::mock(Transformer::class));
        $transformer->shouldReceive('defaultRelations')->andReturn($default = ['baz']);

        $this->builder->resource()->with($relations = ['foo' => function () { }, 'bar'])->transform();

        $model->shouldHaveReceived('load')->with(array_merge($relations, $default))->once();
        $this->transformFactory->shouldHaveReceived('make')->with($this->resource, $this->serializer, [
            'includes' => ['foo', 'bar', 'baz'],
            'excludes' => [],
            'fieldsets' => [],
        ])->once();
    }

    /**
     * Assert that the [transform] method extracts default relationships from transformer and
     * automatically eager loads all relationships even when the relation name contains include parameters.
     */
    public function testTransformMethodExtractsAndEagerLoadsRelationsWhenThereAreRelationParameters()
    {
        $this->transformFactory->shouldReceive('make')->andReturn([]);
        $this->resource->shouldReceive('getData')->andReturn($model = Mockery::mock(Model::class));
        $model->shouldReceive('load')->andReturnSelf();
        $this->resource->shouldReceive('getTransformer')->andReturn($transformer = Mockery::mock(Transformer::class));
        $transformer->shouldReceive('defaultRelations')->andReturn([]);

        $this->builder->resource()->with(['foo:first(aa|bb)', 'bar:second(cc|dd)' => function() {}])->transform();

        // Model should receive the relations names without parameters,
        //  while the transformFactory should receive also parameters to let Fractal use them
        // We must use the Mockery::on() method because with() method will try to do a strict match
        //  for the closure resulting in a failure, because it will check
        //  if it's the same closure reference but no closure are alike, even when they are defined identically.
        // Here we just check that 'bar' element contains a closure.
        $model->shouldHaveReceived('load')->with(Mockery::on(function (array $relations) {
            return ($relations[0] == 'foo') && ($relations['bar'] instanceof \Closure);
        }))->once();
        $this->transformFactory->shouldHaveReceived('make')->with($this->resource, $this->serializer, [
            'includes' => ['foo:first(aa|bb)', 'bar:second(cc|dd)'],
            'excludes' => [],
            'fieldsets' => [],
        ])->once();
    }

    /**
     * Assert that the [transform] method do not eager load relations for which is present an include method.
     */
    public function testTransformMethodDoNotEagerLoadsRelationsForWhichAnIncludeMethodExists()
    {
        $this->transformFactory->shouldReceive('make')->andReturn([]);
        $this->resource->shouldReceive('getData')->andReturn($model = Mockery::mock(Model::class));
        $model->shouldReceive('load')->andReturnSelf();
        // It's not possible to easily mock method_exists with mockery so we must rely on a stub
        $this->resource->shouldReceive('getTransformer')->andReturn(new TransformerWithIncludeMethods());

        $this->builder->resource()->with($relations = ['foo', 'bar'])->transform();

        $model->shouldHaveReceived('load')->with(['foo'])->once();
        $this->transformFactory->shouldHaveReceived('make')->with($this->resource, $this->serializer, [
            'includes' => ['foo', 'bar', 'baz'],
            'excludes' => [],
            'fieldsets' => [],
        ])->once();
    }

    /**
     * Assert that the [only] method sets the filtered fields that are sent to the
     * [TransformFactory].
     */
    public function testOnlyMethodSetsFilteredFieldsSentToFactory()
    {
        $this->transformFactory->shouldReceive('make')->andReturn([]);

        $this->builder->resource()->only($fields = ['foo', 'bar'])->transform();

        $this->transformFactory->shouldHaveReceived('make')->with($this->resource, $this->serializer, [
            'includes' => [],
            'excludes' => [],
            'fieldsets' => $fields,
        ])->once();
    }

    /**
     * Assert that the [only] method allows to be called multiple times and accepts strings
     * as parameters.
     */
    public function testOnlyMethodAllowsMultipleCallsAndStrings()
    {
        $this->transformFactory->shouldReceive('make')->andReturn([]);

        $this->builder->resource()->only('foo')->only('bar', 'baz')->transform();

        $this->transformFactory->shouldHaveReceived('make')->with($this->resource, $this->serializer, [
            'includes' => [],
            'excludes' => [],
            'fieldsets' => ['foo', 'bar', 'baz'],
        ])->once();
    }
}

class TransformerWithIncludeMethods extends Transformer {
    protected $relations = ['foo', 'bar'];

    protected $load = ['baz'];

    public function includeBar() {
        //
    }

    public function includeBaz() {
        //
    }
}