<?php

namespace Drupal\media_external\Form;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\media_library\Form\AddFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a form to find and add external media items.
 */
class ExternalMediaAddForm extends AddFormBase {

  /**
   * The external media search service.
   *
   * @var \Drupal\media_external\ExternalMediaSearch
   */
  protected $externalMediaSearch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('entity_type.manager'),
      $container->get('media_library.ui_builder'),
      $container->get('media_library.opener_resolver')
    );
    $instance->externalMediaSearch = $container->get('media_external.search');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->getBaseFormId() . '_external_media';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    // Add an extra media-library-content wrapper when the form contains a
    // keyword search.
    if ($form_state->get('keyword') && empty($this->getAddedMediaItems($form_state))) {
      // @todo Remove the ID when we can use selectors to replace content via
      //   AJAX in https://www.drupal.org/project/drupal/issues/2821793.
      $form['#prefix'] = '<div id="media-library-content" class="media-library-content"><div id="media-library-add-form-wrapper">';
      $form['#suffix'] = '</div></div>';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildInputElement(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'media-library-add-form--external-media';
    $form['#attributes']['class'][] = 'js-media-library-add-form-external-media';

    // Attach our custom library for the import selection.
    $form['#attached']['library'][] = 'media_external/ui';

    // Add a container to group the input elements for styling purposes.
    $form['input_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'media-library-add-form__input-wrapper',
        ],
      ],
    ];

    $form['input_wrapper']['keyword'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Add by keyword'),
      '#required' => TRUE,
    ];

    $form['input_wrapper']['search'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#button_type' => 'primary',
      '#submit' => ['::searchButtonSubmit'],
      '#ajax' => [
        'callback' => '::updateFormCallback',
        'wrapper' => 'media-library-content',
        // Add a fixed URL to post the form since AJAX forms are automatically
        // posted to <current> instead of $form['#action'].
        // @todo Remove when https://www.drupal.org/project/drupal/issues/2504115
        //   is fixed.
        'url' => Url::fromRoute('media_library.ui'),
        'options' => [
          'query' => $this->getMediaLibraryState($form_state)->all() + [
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
        ],
      ],
    ];

    if ($form_state->get('keyword') && !$form_state::hasAnyErrors()) {
      $provider_name = $this->getMediaType($form_state)->getSource()->getConfiguration()['provider'];
      $form['result_wrapper'] = $this->externalMediaSearch->search($provider_name, $form_state->get('keyword'));

      // Allow the import selection to be set in a hidden field so the selection
      // can be passed between different states of the form. This field is
      // filled via JavaScript so the default value should be empty.
      // @see Drupal.behaviors.MediaLibraryItemSelection
      $form['import_selection'] = [
        '#type' => 'hidden',
        '#default_value' => '',
        '#attributes' => [
          'class' => [
            'js-media-library-add-form-import-selection',
          ],
        ],
      ];

      $form['actions'] = [
        '#type' => 'actions',
        'import' => [
          '#type' => 'submit',
          '#value' => $this->t('Import'),
          '#button_type' => 'primary',
          '#validate' => ['::importButtonValidate'],
          '#submit' => ['::importButtonSubmit'],
          '#ajax' => [
            'callback' => '::updateFormCallback',
            'wrapper' => 'media-library-wrapper',
            // Add a fixed URL to post the form since AJAX forms are
            // automatically posted to <current> instead of $form['#action'].
            // @todo Remove when https://www.drupal.org/project/drupal/issues/2504115
            //   is fixed.
            'url' => Url::fromRoute('media_library.ui'),
            'options' => [
              'query' => $this->getMediaLibraryState($form_state)->all() + [
                FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
              ],
            ],
          ],
        ],
      ];
    }
    return $form;
  }

  /**
   * Submit handler for the search button.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function searchButtonSubmit(array $form, FormStateInterface $form_state): void {
    $form_state->set('keyword', $form_state->getValue('keyword'));
    $form_state->setRebuild();
  }

  /**
   * Validates the form for the import button.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function importButtonValidate(array $form, FormStateInterface $form_state): void {
    $ids = array_filter(explode(',', $form_state->getValue('import_selection')));
    if (empty($ids)) {
      $form_state->setErrorByName('import_selection', $this->t('No items selected.'));
    }
  }

  /**
   * Submit handler for the import button.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function importButtonSubmit(array $form, FormStateInterface $form_state): void {
    $ids = array_filter(explode(',', $form_state->getValue('import_selection')));
    $this->processInputValues($ids, $form, $form_state);
  }

}
