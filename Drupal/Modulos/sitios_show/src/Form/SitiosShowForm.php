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

  public function getFormId(){
    return 'sitios_show_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state){
    //conectar a la otra db
    \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
    $connection = \Drupal\Core\Database\Database::getConnection();
    //se selecciona la base de datos
    $select = Database::getConnection()->select('dependencias', 'd');
    //Se hace un join con tablas necesarias
    $select ->join('dependencias_sitios', 'ds', 'd.id_dependencia = ds.id_dependencia');
    $select ->join('sitios', 's', 's.id_sitio = ds.id_sitio');
    $select ->join('ip_sitios', 'ips', 's.id_sitio = ips.id_sitio');
    $select ->join('dir_ip', 'ip', 'ip.id_ip = ips.id_ip');
    //Se especifican las columnas a leer
    $select->fields('s', array('id_sitio', 'descripcion_sitio', 'url_sitio'))
           ->fields('d', array('nombre_dependencia', 'id_dependencia' ))
           ->fields('ip', array('dir_ip_sitios', 'id_ip'));
    //evitar repetidos
    $select->distinct();
    $select->orderBy('url_sitio','DESC');
    $select = $select->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
    //Se realiza la consulta
    $results = $select->execute();

    $txt = '';
    //se recorren los resultados para después imprimirlos
    foreach ($results as $result){

        $url1 = Url::fromRoute('editar_sitios.content', array('id_s' => $result->id_sitio,
							'id_ip' => $result->id_ip,
                                                        'id_dep' => $result->id_dependencia));
        $project_link1 = Link::fromTextAndUrl('Editar ', $url1);
        $project_link1 = $project_link1->toRenderable();
        $project_link1['#attributes'] = array('class' => array('button'));

        $url = Url::fromRoute('eliminar_sitios.content', array('id_s' => $result->id_sitio));
        $project_link = Link::fromTextAndUrl('Eliminar ', $url);
        $project_link = $project_link->toRenderable();
        $project_link['#attributes'] = array('class' => array('button'));

        $txt .= '<br />';
        $rows[] = [
                $result->id_sitio,
                $result->descripcion_sitio,
                $result->url_sitio,
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
    $form['pager'] = array('#type' => 'pager');
    return $form;

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage(t('The form is working.'));
  }
}
