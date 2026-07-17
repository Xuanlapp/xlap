<?php

namespace Tests\Unit;

use App\Jobs\GenerateSuncatcherWorkflowImage;
use App\Services\Suncatcher\SuncatcherService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class SuncatcherAutomationPipelineTest extends TestCase
{
    public function test_continue_starts_at_the_persisted_failed_step(): void
    {
        $service = (new ReflectionClass(SuncatcherService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($service, 'automationStepsFrom');

        $this->assertSame(
            ['person_b', 'prompt', 'mockup'],
            $method->invoke($service, 'person_b'),
        );
        $this->assertSame(
            ['mockup'],
            $method->invoke($service, 'mockup'),
        );
    }

    public function test_new_pipeline_still_contains_every_step(): void
    {
        $service = (new ReflectionClass(SuncatcherService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($service, 'automationStepsFrom');

        $this->assertSame(
            ['main', 'script', 'person_a', 'person_b', 'prompt', 'mockup'],
            $method->invoke($service, null),
        );
    }

    public function test_stale_mockup_job_stops_before_generating_when_batch_is_no_longer_active(): void
    {
        $service = $this->createMock(SuncatcherService::class);
        $service->expects($this->once())
            ->method('workflowImageBatchSlotShouldRun')
            ->with(22, 'usp')
            ->willReturn(false);
        $service->expects($this->never())->method('markWorkflowImageBatchSlotGenerating');
        $service->expects($this->never())->method('generateWorkflowImage');

        (new GenerateSuncatcherWorkflowImage(7, 22, 'usp'))->handle($service);
    }
}
