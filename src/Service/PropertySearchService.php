<?php

namespace App\Service;

use App\Dto\Request\SearchPropertiesInput;
use App\Dto\Response\BestOfferView;
use App\Dto\Response\PaginationLinks;
use App\Dto\Response\PaginationMeta;
use App\Dto\Response\PropertySearchItem;
use App\Dto\Response\PropertySearchResult;
use App\Repository\PropertyRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PropertySearchService
{
    public function __construct(
        private readonly PropertyRepository $properties,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function search(SearchPropertiesInput $input): PropertySearchResult
    {
        $rows = $this->properties->findWithCheapestOffer(
            $input->checkIn,
            $input->checkOut,
            $input->guests,
            $input->city,
            $input->perPage + 1,
            $input->offset(),
        );

        $hasNextPage = \count($rows) > $input->perPage;
        $items = array_map($this->item(...), \array_slice($rows, 0, $input->perPage));

        return new PropertySearchResult(
            $items,
            new PaginationLinks(
                $input->page > 1 ? $this->link($input, $input->page - 1) : null,
                $hasNextPage ? $this->link($input, $input->page + 1) : null,
            ),
            new PaginationMeta($input->page, $input->perPage, \count($items)),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function item(array $row): PropertySearchItem
    {
        $expiresAt = new \DateTimeImmutable((string) $row['best_offer_expires_at']);

        return new PropertySearchItem(
            (string) $row['code'],
            (string) $row['name'],
            (string) $row['city'],
            new BestOfferView(
                (int) $row['best_offer_id'],
                (string) $row['best_offer_supplier'],
                (int) $row['best_offer_price'],
                (string) $row['best_offer_currency'],
                (int) $row['best_offer_available_units'],
                $expiresAt->setTimezone(new \DateTimeZone('UTC')),
            ),
        );
    }

    private function link(SearchPropertiesInput $input, int $page): string
    {
        $query = [
            'check_in' => $input->checkIn->format('Y-m-d'),
            'check_out' => $input->checkOut->format('Y-m-d'),
            'guests' => $input->guests,
            'per_page' => $input->perPage,
            'page' => $page,
        ];

        if (null !== $input->city) {
            $query['city'] = $input->city;
        }

        return $this->urls->generate('api_properties_index', $query, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
