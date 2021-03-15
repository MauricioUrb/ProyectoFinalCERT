<?php
/*
 * @file
 * Contains \Drupal\hallazgos_show\Form\HallazgosShowForm
 */
namespace Drupal\hallazgos_show\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\Link;
/*
 *
 */
class HallazgosShowForm extends FormBase{
  /*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'hallazgos_show_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state){
    //conectar a la otra db
    \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
    $connection = \Drupal\Core\Database\Database::getConnection();
    //Se selecciona la tabla en modo lectura
    $select = Database::getConnection()->select('hallazgos', 'h');
    //Se especifican las columnas a leer
    $select->fields('h', array('id_hallazgo'))
           ->fields('h', array('nombre_hallazgo_vulnerabilidad'))
           ->fields('h', array('descripcion_hallazgo'))
           ->fields('h', array('solucion_recomendacion_halazgo'))
           ->fields('h', array('referencias_hallazgo'))
           ->fields('h', array('recomendacion_general_hallazgo'))
           ->fields('h', array('nivel_cvss'))
           ->fields('h', array('vector_cvss'))
           ->fields('h', array('enlace_cvss'))
           ->fields('h', array('r_ejecutivo_hallazgo'))
           ->fields('h', array('solucion_corta'));
    $select->orderBy('nombre_hallazgo_vulnerabilidad');
    $select = $select->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
    //Se realiza la consulta
    $results = $select->execute();

    $txt = '';
    //se recorren los resultados para después imprimirlos
    foreach ($results as $result){
      $url1 = Url::fromRoute('editar_hallazgos.content', array('id_h' => $result->id_hallazgo));
      $project_link1 = Link::fromTextAndUrl('Editar ', $url1);
      $project_link1 = $project_link1->toRenderable();
      $project_link1['#attributes'] = array('class' => array('button'));

      $url = Url::fromRoute('eliminar_hallazgos.content', array('id_h' => $result->id_hallazgo));
      $project_link = Link::fromTextAndUrl('Eliminar ', $url);
      $project_link = $project_link->toRenderable();
      $project_link['#attributes'] = array('class' => array('button'));

      $txt .= '<br />';
      $rows[$result->id_hallazgo] = [
                $result->id_hallazgo,
                $result->nombre_hallazgo_vulnerabilidad,
                $result->descripcion_hallazgo,
                $result->solucion_recomendacion_halazgo,
                $result->referencias_hallazgo,
                $result->recomendacion_general_hallazgo,
                $result->nivel_cvss,
                $result->vector_cvss,
                $result->enlace_cvss,
                $result->r_ejecutivo_hallazgo,
                $result->solucion_corta,
                //Markup::create('<a href="/node/add/page">Editar</a>'),
                //Markup::create('<a href="/node/add/page">Eliminar</a>'),
                render($project_link1),
                render($project_link),
        ];
    }

    //$form['txt']['#markup'] = $txt;
    //Se asignan titulos a cada columna
    $header = [
      'id' => t('ID'),
      'name' => t('Nombre'),
      'description' => t('Descripcion'),
      'sol' => t('Sol/Recomendación'),
      'ref' => t('Referencias'),
      'recom' => t('Recomendacion'),
      'nivel' => t('Nivel'),
      'vector' => t('Vector'),
      'enlace' => t('Enlace'),
      'r_ejecutivo' => t('Resumen'),
      'sol_corta' => t('Sol Corta'),
      'edit' => t('Editar'),
      'del' => t('Eliminar'),
    ];
    //se construye la tabla para mostrar
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => t('Nada para mostrar.'),
    ];

    $form = [
      '#type' => '#markup',
      '#markup' => render($build)
    ];
    //regresar a bd la default
    \Drupal\Core\Database\Database::setActiveConnection();
    $form['pager'] = array('#type' => 'pager');
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage(t('The form is working.'));
  }
}
