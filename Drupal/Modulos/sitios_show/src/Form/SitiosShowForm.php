<?php
/*
 * @file
 * Contains \Drupal\sitios_show\Form\SitiosShowForm
 */
namespace Drupal\sitios_show\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\Link;

/*
 *
 */
class SitiosShowForm extends FormBase{
  /*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'sitios_show_form';
  }
  /*
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state){
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->nid->value;

    //Coneccion a la BD
    $node = \Drupal::routeMatch()->getParameter('node');
    //Se selecciona la tabla en modo lectura

    $select = Database::getConnection()->select('dependencias', 'd');
    //Se hace un join con tablas necesarias
    $select ->join('dependencias_sitios', 'ds', 'd.id_dependencia = ds.id_dependencia');
    $select ->join('sitios', 's', 's.id_sitios = ds.id_sitios');
    $select ->join('ip_sitios', 'ips', 's.id_sitios = ips.id_sitios');
    $select ->join('dir_ip', 'ip', 'ip.id_ip = ips.id_ip');
    //Se especifican las columnas a leer
    $select->fields('s', array('id_sitios', 'descripcion_sitios', 'url_sitios'))
           ->fields('d', array('nombre_dependencia'))
           ->fields('ip', array('dir_ip_sitios'));
    //Se realiza la consulta
    $results = $select->execute();

    $txt = '';
    //se recorren los resultados para después imprimirlos
    foreach ($results as $result){
/*        $txt .= ' ' . $result->id_sitios;
//      $txt .= ' ' . $result->id_ip;
        $txt .= ' ' . $result->descripcion_sitios;
        $txt .= ' ' . $result->url_sitios;
        $txt .= ' ' . $result->nombre_dependencia;
        $txt .= ' ' . $result->dir_ip_sitios;
        $txt .= ' ' . '<a href="/editar/sitios">Editar</a>';*/
//        $txt .= ' ' . '<a href="/node/add/page">Eliminar</a>';

        $url1 = Url::fromRoute('editar_sitios.content', array('id_s' => $result->id_sitios));
        $project_link1 = Link::fromTextAndUrl('Editar ', $url1);
        $project_link1 = $project_link1->toRenderable();
        $project_link1['#attributes'] = array('class' => array('button'));

        $url = Url::fromRoute('eliminar_sitios.content', array('id_s' => $result->id_sitios));
        $project_link = Link::fromTextAndUrl('Eliminar ', $url);
        $project_link = $project_link->toRenderable();
        $project_link['#attributes'] = array('class' => array('button'));

        $txt .= '<br />';
        $rows[$result->id_sitios] = [
                $result->id_sitios,
                $result->descripcion_sitios,
                $result->url_sitios,
                $result->nombre_dependencia,
                $result->dir_ip_sitios,
//              Markup::create('<a href="/hallazgos/alta">Editar</a>'),
//              Markup::create('<a href="/node/add/page">Eliminar</a>'),
                render($project_link1),
                render($project_link),
        ];
    }
    $form['txt']['#markup'] = $txt;
    //Se asignan titulos a cada columna
    $header = [
      'id' => t('ID'),
      'title' => t('Descripcion'),
      'url' => t('URL'),
      'dep' => t('Dependencia'),
      'ip' => t('IP'),
      'action1' => t('Editar'),
      'action2' => t('Eliminar'),
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