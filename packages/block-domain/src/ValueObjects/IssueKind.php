<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * Classifies a problem found while resolving a block, so editors and logs can react to each kind differently.
 *
 * @since 0.1.0
 */
enum IssueKind: string
{
    case UnknownType = 'unknown-type';
    case MigrationFailed = 'migration-failed';
    case UnknownDataSource = 'unknown-data-source';
    case InvalidBinding = 'invalid-binding';
    case PrivateBinding = 'private-binding';
    case InvalidSettings = 'invalid-settings';
    case DisallowedChild = 'disallowed-child';
    case InvalidView = 'invalid-view';
    case StepFailed = 'step-failed';
    case BudgetExceeded = 'budget-exceeded';
    case MissingOutput = 'missing-output';
    case OutputFailed = 'output-failed';
    case InvalidAttribute = 'invalid-attribute';
    case UnknownCondition = 'unknown-condition';
    case InvalidCondition = 'invalid-condition';
    case UnknownCollection = 'unknown-collection';
    case InvalidCollection = 'invalid-collection';
}
