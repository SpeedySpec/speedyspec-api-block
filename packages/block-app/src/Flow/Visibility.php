<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Flow;

use SpeedySpec\Block\Domain\Contracts\ConditionRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\GateStepInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Contracts\ValidateStepInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Decorator;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use Throwable;

/**
 * Shows or hides any block by rules stored on it: groups of conditions, with no expressions.
 *
 * A block is shown when it has no groups, or when every rule of at least one group holds; a group with no rules
 * never holds. A rule that cannot be
 * judged (an unknown condition, arguments the condition refuses, a condition that fails) counts as not holding
 * even when negated, so a mistake hides content from visitors rather than exposing it; an editor sees why.
 *
 * The rules decide what is resolved and drawn. They are not applied to a client export, which carries a document's
 * blocks as stored: a host must not send the export of a document whose rules hide something from the visitor.
 *
 * @since 0.1.0
 */
class Visibility implements GateStepInterface, ValidateStepInterface
{
    public const string NAMESPACE = 'core/visibility';

    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly ConditionRegistryInterface $conditions,
        private readonly SchemaValidatorInterface $validator,
    )
    {
    }

    /**
     * Builds the decorator that lets every block type carry visibility rules.
     *
     * @since 0.1.0
     */
    public static function decorator(): Decorator
    {
        return new Decorator(self::NAMESPACE, [
            'type' => 'object',
            'properties' => [
                'groups' => [
                    'type' => 'array',
                    'default' => [],
                    'title' => 'Show when',
                    'description' => 'Shown when every rule of any one group holds. No groups: always shown.',
                    'items' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'items' => [
                            'type' => 'object',
                            'required' => ['condition'],
                            'properties' => [
                                'condition' => ['type' => 'string', 'title' => 'Condition'],
                                'arguments' => ['type' => 'object', 'default' => [], 'title' => 'Arguments'],
                                'negate' => ['type' => 'boolean', 'default' => false, 'title' => 'Is not'],
                            ],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ],
            'additionalProperties' => false,
        ]);
    }

    public function shown(Block $block, BlockType $type, Context $context, Mode $mode): bool
    {
        $groups = $block->extensions[self::NAMESPACE]['groups'] ?? [];

        if ($groups === [])
        {
            return true;
        }

        if (!is_array($groups))
        {
            return false;
        }

        foreach ($groups as $group)
        {
            if (is_array($group) && $this->groupHolds($group, $context, $mode))
            {
                return true;
            }
        }

        return false;
    }

    public function validate(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): array
    {
        $groups = $block->extensions[self::NAMESPACE]['groups'] ?? [];
        $issues = [];

        foreach (is_array($groups) ? array_values($groups) : [] as $g => $group)
        {
            foreach (is_array($group) ? array_values($group) : [] as $r => $rule)
            {
                $problem = is_array($rule) ? $this->problem($rule) : null;

                if ($problem !== null)
                {
                    $issues[] = new Issue(
                        $block->id,
                        $problem[0],
                        $problem[1],
                        sprintf('@%s:groups.%d.%d', self::NAMESPACE, $g, $r),
                    );
                }
            }
        }

        return $issues;
    }

    /**
     * @param array<array-key, mixed> $group
     *
     * @since 0.1.0
     */
    private function groupHolds(array $group, Context $context, Mode $mode): bool
    {
        // A group with no rules restricts nothing; taken as holding, it would make a restricted block public.
        if ($group === [])
        {
            return false;
        }

        foreach ($group as $rule)
        {
            if (!is_array($rule) || $this->problem($rule) !== null)
            {
                return false;
            }

            try
            {
                $holds = $this->conditions->get((string) $rule['condition'])
                    ->holds($this->arguments($rule), $context, $mode);
            }
            catch (Throwable)
            {
                return false;
            }

            if ($holds === (bool) ($rule['negate'] ?? false))
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Finds why a rule cannot be judged at all.
     *
     * @param array<array-key, mixed> $rule
     *
     * @return array{0: IssueKind, 1: string}|null
     *   Null when the rule names a registered condition and its arguments fit.
     *
     * @since 0.1.0
     */
    private function problem(array $rule): ?array
    {
        $name = is_string($rule['condition'] ?? null) ? $rule['condition'] : '';
        $condition = $this->conditions->get($name);

        if ($condition === null)
        {
            return [IssueKind::UnknownCondition, sprintf('Condition "%s" is not registered.', $name)];
        }

        $errors = $this->validator->validate($this->arguments($rule), $condition->argumentsSchema())->errors();

        if ($errors !== [])
        {
            return [IssueKind::InvalidCondition, sprintf(
                'The arguments for condition "%s" do not match its schema: %s',
                $name,
                $errors[0]->message,
            )];
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $rule
     *
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function arguments(array $rule): array
    {
        return is_array($rule['arguments'] ?? null) ? $rule['arguments'] : [];
    }
}
