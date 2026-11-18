<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Resolving;

use SpeedySpec\Block\Domain\Contracts\DecoratorRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\ValidateStepInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use Throwable;

/**
 * Checks resolved settings and extensions against their schemas, so a block that cannot be drawn is stopped.
 *
 * @since 0.1.0
 */
class ValidateStage
{
    /**
     * Wires the stage to what it checks against.
     *
     * @param DecoratorRegistryInterface $decorators
     *   Supplies the extension schemas for the type.
     * @param SchemaValidatorInterface $validator
     *   Checks values against schemas.
     * @param StepRegistryInterface $steps
     *   Supplies the validate steps.
     */
    public function __construct(
        private readonly DecoratorRegistryInterface $decorators,
        private readonly SchemaValidatorInterface $validator,
        private readonly StepRegistryInterface $steps,
    )
    {
    }

    /**
     * Validates one block; schema failures stop it, while issues from validate steps, or their failures, are appended.
     *
     * Validate steps run only when the schema checks passed, so they can rely on well-formed settings.
     *
     * @since 0.1.0
     */
    public function run(StageOutcome $resolved, BlockType $type, Context $context, Mode $mode): StageOutcome
    {
        if (!$resolved->continue)
        {
            return $resolved;
        }

        $blockId = $resolved->block->id;
        $found = $this->failures($blockId, $resolved->settings, $type->settingsSchema, null);

        foreach ($this->decorators->for($type->name) as $decorator)
        {
            array_push($found, ...$this->failures(
                $blockId,
                $resolved->extensions[$decorator->namespace] ?? [],
                $decorator->settingsSchema,
                $decorator->namespace,
            ));
        }

        if ($found !== [])
        {
            return $resolved->with(issues: [...$resolved->issues, ...$found], continue: false);
        }

        $block = $resolved->workingBlock();

        foreach ($this->steps->for(ValidateStepInterface::class) as $step)
        {
            try
            {
                $returned = $step->validate($block, $type, $resolved->settings, $context, $mode);
            }
            catch (Throwable $e)
            {
                $found[] = new Issue($blockId, IssueKind::StepFailed, sprintf(
                    'Validate step %s failed with %s.',
                    get_debug_type($step),
                    $e::class,
                ));

                continue;
            }

            foreach ($returned as $issue)
            {
                if ($issue instanceof Issue)
                {
                    $found[] = $issue;
                }
            }
        }

        return $resolved->with(issues: [...$resolved->issues, ...$found]);
    }

    /**
     * Turns each failed rule into an issue, prefixing the path with `@namespace:` for an extension.
     *
     * A failure at the root of the settings has no single setting at fault, so its path is null.
     *
     * @param array<string, mixed> $schema
     *
     * @return list<Issue>
     */
    private function failures(string $blockId, array $value, array $schema, ?string $namespace): array
    {
        $issues = [];

        foreach ($this->validator->validate($value, $schema)->errors() as $error)
        {
            if ($namespace === null)
            {
                $path = $error->path === '' ? null : $error->path;
            }
            else
            {
                $path = $error->path === '' ? '@' . $namespace : '@' . $namespace . ':' . $error->path;
            }

            $issues[] = new Issue($blockId, IssueKind::InvalidSettings, $error->message, $path);
        }

        return $issues;
    }
}
