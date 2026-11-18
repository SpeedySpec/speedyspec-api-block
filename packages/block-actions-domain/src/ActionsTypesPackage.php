<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Actions\Domain;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\MediaKind;

/**
 * Defines the blocks that ask a visitor to do something: buttons, calls to action, testimonials, pricing, ratings
 * and social links.
 *
 * How they are drawn is another package's. A media library is optional: without one a testimonial has no photo,
 * and every other block is unaffected. The settings are fixed; changing one needs a migration.
 *
 * @since 0.1.0
 */
class ActionsTypesPackage implements BlockPackageInterface
{
    /**
     * @param MediaLibraryInterface|null $library
     *   Where a testimonial's photo is looked up; null on a host with no media.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly ?MediaLibraryInterface $library = null)
    {
    }

    /**
     * Builds the package with the container's media library, when it has one.
     *
     * @since 0.1.0
     */
    public static function from(BlockServiceContainer $container): self
    {
        return new self(
            $container->has(MediaLibraryInterface::class) ? $container->get(MediaLibraryInterface::class) : null,
        );
    }

    public function name(): string
    {
        return 'core/actions-types';
    }

    public function title(): string
    {
        return 'Actions';
    }

    public function description(): string
    {
        return 'Buttons, calls to action, testimonials, pricing, ratings and social links.';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        $type = fn (array $definition) => $registrar->type(
            BlockType::fromArray(['category' => 'actions', ...$definition]),
        );
        $text = fn (string $default, int $max, string $title): array => [
            'type' => 'string',
            'default' => $default,
            'maxLength' => $max,
            'title' => $title,
        ];
        $rich = fn (string $title): array => [
            'type' => 'string',
            'default' => '',
            'x-control' => 'rich-text',
            'title' => $title,
        ];
        $choice = fn (array $options, string $default, string $title): array => [
            'enum' => $options,
            'default' => $default,
            'title' => $title,
        ];

        $type([
            'name' => 'core/button',
            'title' => 'Button',
            'description' => 'A link that looks like a button.',
            'keywords' => ['link', 'cta'],
            'settings' => ['type' => 'object', 'properties' => [
                'text' => $text('Learn more', 80, 'Text'),
                'link' => [
                    ...$text('', 2000, 'Link to'),
                    'description' => 'A web address, an email (mailto:), a phone number (tel:) or a page on this site.',
                ],
                'newTab' => ['type' => 'boolean', 'default' => false, 'title' => 'Open in a new tab'],
                'variant' => $choice(['primary', 'secondary', 'outline', 'plain'], 'primary', 'Look'),
                'size' => $choice(['small', 'medium', 'large'], 'medium', 'Size'),
            ]],
            'examples' => [['text' => 'Get started', 'link' => '/start']],
        ]);

        $type([
            'name' => 'core/buttons',
            'title' => 'Buttons',
            'description' => 'A row of buttons.',
            'settings' => ['type' => 'object', 'properties' => [
                'align' => $choice(['start', 'center', 'end', 'between'], 'start', 'Line up'),
            ]],
            'children' => ['core/button'],
            'examples' => [['align' => 'start']],
        ]);

        $type([
            'name' => 'core/call-to-action',
            'title' => 'Call to action',
            'description' => 'A heading, a few words and the buttons that act on them.',
            'keywords' => ['cta', 'banner', 'hero'],
            'settings' => ['type' => 'object', 'properties' => [
                'heading' => $text('Ready to start?', 200, 'Heading'),
                'level' => [
                    'type' => 'integer',
                    'default' => 2,
                    'minimum' => 2,
                    'maximum' => 4,
                    'title' => 'Heading level',
                ],
                'text' => $rich('Text'),
            ]],
            'children' => ['core/buttons', 'core/button'],
            'examples' => [['heading' => 'Ready to start?', 'text' => 'It takes a minute.']],
        ]);

        $type([
            'name' => 'core/testimonial',
            'title' => 'Testimonial',
            'description' => 'What someone said about you, with their name.',
            'keywords' => ['review', 'quote', 'customer'],
            'settings' => ['type' => 'object', 'properties' => [
                'quote' => $rich('What they said'),
                'name' => $text('', 120, 'Name'),
                'role' => $text('', 120, 'Role or company'),
                'media' => [
                    'type' => 'string',
                    'default' => '',
                    'x-control' => 'media',
                    'x-media-kind' => MediaKind::Image->value,
                    'title' => 'Photo',
                ],
            ]],
            'examples' => [['quote' => 'It just works.', 'name' => 'Ann Lee', 'role' => 'Designer']],
        ]);
        $registrar->behaviour(BlockName::fromString('core/testimonial'), new TestimonialBehaviour($this->library));

        $type([
            'name' => 'core/pricing-table',
            'title' => 'Pricing table',
            'description' => 'Plans side by side.',
            'keywords' => ['plans', 'prices', 'tiers'],
            'settings' => ['type' => 'object', 'properties' => [
                'columns' => [
                    'type' => ['integer', 'object'],
                    'default' => 3,
                    'minimum' => 1,
                    'maximum' => 4,
                    'additionalProperties' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 4],
                    'x-control' => 'responsive',
                    'title' => 'Columns',
                ],
            ]],
            'children' => ['core/pricing-plan'],
            'examples' => [['columns' => 3]],
        ]);

        $type([
            'name' => 'core/pricing-plan',
            'title' => 'Pricing plan',
            'description' => 'One plan: its price, what it includes and a button.',
            'settings' => ['type' => 'object', 'properties' => [
                'name' => $text('Plan', 80, 'Name'),
                'price' => [...$text('$0', 20, 'Price'), 'description' => 'As it should read, such as $19 or Free.'],
                'period' => $text('per month', 40, 'Period'),
                'description' => $rich('Description'),
                'features' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'maxLength' => 200],
                    'default' => ['First feature', 'Second feature'],
                    'maxItems' => 30,
                    'x-control' => 'list',
                    'title' => 'Features',
                ],
                'highlighted' => ['type' => 'boolean', 'default' => false, 'title' => 'Stand out'],
                'badge' => [...$text('', 40, 'Badge'), 'description' => 'Such as Most popular. Optional.'],
            ]],
            'parents' => ['core/pricing-table'],
            'children' => ['core/button', 'core/buttons'],
            'examples' => [['name' => 'Starter', 'price' => '$9', 'features' => ['One site', 'Email support']]],
        ]);

        $type([
            'name' => 'core/rating',
            'title' => 'Rating',
            'description' => 'A score out of a number of stars, said in words too.',
            'keywords' => ['stars', 'score', 'review'],
            'settings' => ['type' => 'object', 'properties' => [
                'value' => ['type' => 'number', 'default' => 5, 'minimum' => 0, 'maximum' => 10, 'title' => 'Score'],
                'outOf' => ['type' => 'integer', 'default' => 5, 'minimum' => 1, 'maximum' => 10, 'title' => 'Out of'],
                'label' => [...$text('', 120, 'Label'), 'description' => 'What is rated, such as Service. Optional.'],
            ]],
            'examples' => [['value' => 4.5, 'outOf' => 5]],
        ]);
        $registrar->behaviour(BlockName::fromString('core/rating'), new RatingBehaviour());

        $type([
            'name' => 'core/social-links',
            'title' => 'Social links',
            'description' => 'Links to your profiles elsewhere.',
            'keywords' => ['social', 'profiles', 'follow'],
            'settings' => ['type' => 'object', 'properties' => [
                'size' => $choice(['small', 'medium', 'large'], 'medium', 'Size'),
            ]],
            'children' => ['core/social-link'],
            'examples' => [['size' => 'medium']],
        ]);

        $type([
            'name' => 'core/social-link',
            'title' => 'Social link',
            'description' => 'A link to one profile.',
            'settings' => ['type' => 'object', 'properties' => [
                'service' => $choice(SocialServices::NAMES, 'website', 'Service'),
                'url' => [
                    ...$text('', 2000, 'Link'),
                    'description' => 'An https link to the profile, or mailto: for email.',
                ],
            ]],
            'parents' => ['core/social-links'],
            'examples' => [['service' => 'github', 'url' => 'https://github.com/example']],
        ]);
        $registrar->behaviour(BlockName::fromString('core/social-link'), new SocialLinkBehaviour());
    }
}
