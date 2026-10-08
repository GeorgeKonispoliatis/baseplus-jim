<?php

namespace Drupal\media_external;

use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media_external\Plugin\ExternalMediaProviderManager;

/**
 * Provides search results for the external media add form.
 */
class ExternalMediaSearch {

  use StringTranslationTrait;

  /**
   * The external media provider manager.
   *
   * @var \Drupal\media_external\Plugin\ExternalMediaProviderManager
   */
  protected $externalMediaProviderManager;

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * The external media cache wrapper.
   *
   * @var \Drupal\media_external\ExternalMediaCacheWrapperInterface
   */
  protected $cacheWrapper;

  /**
   * ExternalMediaSearch constructor.
   *
   * @param \Drupal\media_external\Plugin\ExternalMediaProviderManager $external_provider_manager
   *   The external media provider manager.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager.
   * @param \Drupal\media_external\ExternalMediaCacheWrapperInterface $cache_wrapper
   *   The external media cache wrapper.
   */
  public function __construct(ExternalMediaProviderManager $external_provider_manager, PagerManagerInterface $pager_manager, ExternalMediaCacheWrapperInterface $cache_wrapper) {
    $this->externalMediaProviderManager = $external_provider_manager;
    $this->pagerManager = $pager_manager;
    $this->cacheWrapper = $cache_wrapper;
  }

  /**
   * Search for external media items.
   *
   * @param string $provider_name
   *   The external media provider name.
   * @param string $keyword
   *   The search keyword.
   *
   * @return array
   *   An array of external media results.
   */
  public function search(string $provider_name, string $keyword): array {
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'media-library-results',
        'class' => [
          'media-library-add-form__result-wrapper',
        ],
      ],
    ];

    $build['results'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'media-library-view',
          'media-library-views-form__rows',
        ],
      ],
      '#tree' => TRUE,
    ];

    // Fetch the results for the current page.
    /** @var \Drupal\media_external\Plugin\ExternalMediaProviderInterface $provider */
    $provider = $this->externalMediaProviderManager->createInstance($provider_name);
    $results = $this->cacheWrapper->search($provider, $keyword, $this->pagerManager->findPage());

    // Update the pager.
    $this->pagerManager->createPager($results['total'], $results['per_page']);

    foreach ($results['results'] as $result) {
      $id = $result->getId();
      $build['results'][$id] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'media-library-item',
            'media-library-item--grid',
            'js-media-library-import-item',
            'js-click-to-select',
          ],
        ],
      ];
      $build['results'][$id]['select_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'media-library-item__click-to-select-checkbox',
            'js-click-to-select-checkbox',
          ],
        ],
      ];
      $build['results'][$id]['select_wrapper']['select'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Select @label', [
          '@label' => $result->getDescription() ?: $result->getAlt() ?: $id,
        ]),
        '#title_display' => 'invisible',
        '#return_value' => $id,
      ];
      $build['results'][$id]['image_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'js-click-to-select-trigger',
            'media-library-item__click-to-select-trigger',
          ],
        ],
      ];
      $build['results'][$id]['image_wrapper']['image'] = [
        '#theme' => 'image',
        '#uri' => $result->getThumbnailUrl(),
      // '#style_name' => 'media_library',
        '#alt' => $result->getAlt(),
      ];
    }

    $build['pager'] = [
      '#type' => 'pager',
      '#route_name' => 'media_external.search',
      '#route_parameters' => [
        'provider_name' => $provider_name,
        'keyword' => $keyword,
      ],
    ];

    return $build;
  }

}
