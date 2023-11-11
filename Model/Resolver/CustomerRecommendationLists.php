<?php

namespace SwiftOtter\FriendRecommendations\Model\Resolver;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\CustomerGraphQl\Model\Customer\GetCustomer;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use SwiftOtter\FriendRecommendations\Api\Data\RecommendationListInterface;
use SwiftOtter\FriendRecommendations\Model\RecommendationListRepository;

class CustomerRecommendationLists implements ResolverInterface
{
    private GetCustomer $getCustomer;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private RecommendationListRepository $recommendationListRepository;
    private ProductRepositoryInterface $productRepository;
    private ImageHelper $imageHelper;

    public function __construct(
        GetCustomer $getCustomer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RecommendationListRepository $recommendationListRepository,
        ProductRepositoryInterface $productRepository,
        ImageHelper $imageHelper
    ) {
        $this->getCustomer = $getCustomer;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->recommendationListRepository = $recommendationListRepository;
        $this->productRepository = $productRepository;
        $this->imageHelper = $imageHelper;
    }

    /**
     * {@inheritdoc}
     * @param  ContextInterface $context
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $isLoggedIn = $context->getExtensionAttributes()->getIsCustomer();
        if (!$isLoggedIn) {
            throw new GraphQlNoSuchEntityException(
                __("Please, make sure to log in first in order to see the recommendation list.")
            );
        }
        $customer = $this->getCustomer->execute($context);
        $email = $customer->getEmail();
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('email', $email)->create();

        $recommendLists = $this->recommendationListRepository->getList($searchCriteria)->getItems();
        if (empty($recommendLists)) {
            throw new GraphQlNoSuchEntityException(
                __('No recommendation list for this user logged in.')
            );
        }
        $getProducts = $info->getFieldSelection()['products'] ?? false;
        $recommendListData = [];

        foreach ($recommendLists as $recommendList) {
            $recommendListData[] = [
                'friendName' => $recommendList->getFriendName(),
                'title' =>  $recommendList->getTitle(),
                'note'  =>  $recommendList->getNote(),
                'products'  =>  $getProducts ? $this->getRecommendationListProductIds($recommendList) : []
            ];
        }

        return $recommendListData;
    }

    /**
     * @param RecommendationListInterface $recommendList
     * @return array
     */
    private function getRecommendationListProductIds(RecommendationListInterface $recommendList): array
    {
        $listProducts = [];
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('recommendation_list_ids', [$recommendList->getId()])
            ->create();
        $products = $this->productRepository->getList($searchCriteria)->getItems();
        /** @var Product $product */
        foreach ($products as $product) {
            $listProducts[] =  [
                'sku'   =>  $product->getSku(),
                'name'  =>  $product->getName(),
                'thumbnailUrl' =>  $this->imageHelper->init($product, 'product_thumbnail_image')->getUrl(),
            ];
        }
        return $listProducts;
    }
}
