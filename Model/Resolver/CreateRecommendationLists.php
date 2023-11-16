<?php

namespace SwiftOtter\FriendRecommendations\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use SwiftOtter\FriendRecommendations\Api\Data\RecommendationListInterfaceFactory as RecommendationListFactory;
use SwiftOtter\FriendRecommendations\Api\Data\RecommendationListProductInterface;
use SwiftOtter\FriendRecommendations\Api\Data\RecommendationListProductInterfaceFactory as RecommendationListProductFactory;
use SwiftOtter\FriendRecommendations\Model\RecommendationListProductRepository;
use SwiftOtter\FriendRecommendations\Model\RecommendationListRepository;

class CreateRecommendationLists implements ResolverInterface
{
    /**
     * @var RecommendationListRepository
     */
    private RecommendationListRepository $recommendationListRepository;
    /**
     * @var RecommendationListFactory
     */
    private RecommendationListFactory $recommendationListFactory;
    /**
     * @var RecommendationListProductRepository
     */
    private RecommendationListProductRepository $recommendationListProductRepository;
    /**
     * @var RecommendationListProductFactory
     */
    private RecommendationListProductFactory $recommendationListProductFactory;

    /**
     * @param RecommendationListRepository $recommendationListRepository
     * @param RecommendationListProductRepository $recommendationListProductRepository
     * @param RecommendationListFactory $recommendationListFactory
     * @param RecommendationListProductFactory $recommendationListProductFactory
     */
    public function __construct(
        RecommendationListRepository $recommendationListRepository,
        RecommendationListProductRepository $recommendationListProductRepository,
        RecommendationListFactory $recommendationListFactory,
        RecommendationListProductFactory $recommendationListProductFactory
    )
    {
        $this->recommendationListRepository = $recommendationListRepository;
        $this->recommendationListFactory = $recommendationListFactory;
        $this->recommendationListProductRepository = $recommendationListProductRepository;
        $this->recommendationListProductFactory = $recommendationListProductFactory;
    }

    /**
     * {@inheritdoc}
     * @param ContextInterface $context
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
                __("Please, make sure to log in first in order to post the recommendation list.")
            );
        }
        $validInputs = $this->validateInputs($args);
        if (!$validInputs) {
            throw new GraphQlInputException(
                __("PLease check the required inputs in order to add a recommendation list.")
            );
        }
        $email = $args['email'];
        $friendName = $args['friendName'];
        $productSkus = $args['productSkus'];
        $title = $args['title'] ?? '';
        $note = $args['note'] ?? '';

        $recommendationList = $this->recommendationListFactory->create();
        $recommendationList->setEmail($email);
        $recommendationList->setFriendName($friendName);
        $recommendationList->setTitle($title);
        $recommendationList->setNote($note);

        $savedRecomendationList = $this->recommendationListRepository->save($recommendationList);

        if (!empty($productSkus)) {
            foreach ($productSkus as $productSku) {
                $recommendationListProduct = $this->recommendationListProductFactory->create();
                $recommendationListProduct->setSku($productSku);
                $recommendationListProduct->setListId($savedRecomendationList->getId());
                $this->recommendationListProductRepository->save($recommendationListProduct);
            }
        }
        return [
            'email' => $savedRecomendationList->getEmail(),
            'friendName' => $savedRecomendationList->getFriendName(),
            'title' =>  $savedRecomendationList->getTitle(),
            'note'  =>  $savedRecomendationList->getNote()
        ];
    }

    /**
     * @param array $args
     * @return bool
     */
    private function validateInputs(array $args): bool
    {
        $flag = true;
        if (empty($args['email']) || empty($args['friendName'] || empty($args['productSkus']))) {
            $flag = false;
        }
        return $flag;
    }
}
