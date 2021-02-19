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
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->nid->value;

    //Coneccion a la BD
    $node = \Drupal::routeMatch()->getParameter('node');
    //Se selecciona la tabla en modo lectura
    $select = Database::getConnection()->select('hallazgos', 'h');
    //Se especifican las columnas a leer
    $select->fields('h', array('id_hallazgos'))
           ->fields('h', array('nombre_hallazgo_vulnerabilidad'))
           ->fields('h', array('descripcion_hallazgo'))
           ->fields('h', array('solucion_recomendacion_halazgo'))
           ->fields('h', array('referencias_hallazgo'))
           ->fields('h', array('recomendacion_general_hallazgo'))
           ->fields('h', array('nivel_cvss'))
           ->fields('h', array('vector_cvss'))
           ->fields('h', array('enlace_cvss'))
           ->fields('h', array('r_ejecutivo_hallazgo'));
    //Se realiza la consulta
    $results = $select->execute();

    $txt = '';
    //se recorren los resultados para después imprimirlos
    foreach ($results as $result){
/*      $txt .= ' ' . $result->id_hallazgos;
      $txr .= ' ' . $result->nombre_hallazgo_vulnerabilidad;
      $txt .= ' ' . $result->descripcion_hallazgo;
      $txt .= ' ' . $result->solucion_recomendacion_halazgo;
      $txt .= ' ' . $result->referencias_hallazgo;
      $txt .= ' ' . $result->nivel_cvss;*/
//      $txt .= ' ' . '<a href="/editar/hallazgos">Editar</a>';

      $url1 = Url::fromRoute('editar_hallazgos.content', array('id_h' => $result->id_hallazgos));
      $project_link1 = Link::fromTextAndUrl('Editar ', $url1);
      $project_link1 = $project_link1->toRenderable();
      $project_link1['#attributes'] = array('class' => array('button'));

      $url = Url::fromRoute('eliminar_hallazgos.content', array('id_h' => $result->id_hallazgos));
      $project_link = Link::fromTextAndUrl('Eliminar ', $url);
      $project_link = $project_link->toRenderable();
      $project_link['#attributes'] = array('class' => array('button'));
/*      $form[$rev] = array(
            '#markup' => render($project_link),
      );*/

      $txt .= '<br />';
      $rows[$result->id_hallazgos] = [
                $result->id_hallazgos,
                $result->nombre_hallazgo_vulnerabilidad,
                $result->descripcion_hallazgo,
                $result->solucion_recomendacion_halazgo,
                $result->referencias_hallazgo,
                $result->recomendacion_general_hallazgo,
                $result->nivel_cvss,
                $result->vector_cvss,
                $result->enlace_cvss,
                $result->r_ejecutivo_hallazgo,
                //Markup::create('<a href="/node/add/page">Editar</a>'),
                //Markup::create('<a href="/node/add/page">Eliminar</a>'),
                render($project_link1),
                render($project_link),
        ];
    }

    $form['txt']['#markup'] = $txt;
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

    return $form;

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage(t('The form is working.'));
  }
}