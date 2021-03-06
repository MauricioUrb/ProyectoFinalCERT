<?php
/*
 * @file
 * Contains \Drupal\mostrar_imagen\Form\MostrarImagenForm
 */
namespace Drupal\mostrar_imagen\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\Link;

class MostrarImagenForm extends FormBase{

  public function getFormId(){
    return 'mostrar_imagen_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state){
    // se hace la conexion a la base de datos
    $connection = \Drupal::service('database');

    $select = $connection->select('file_managed', 'fm')
                ->fields('fm', array('fid', 'filename', 'descripcion'));

                //sustituir el 7 por la variable del id
    $select->condition('id_rev_sh', 7);
    //Se realiza la consulta
    $results = $select->execute();

    foreach ($results as $result){

      $url1 = Url::fromRoute('editar_imagen.content', array('fid' => $result->fid,));
        $project_link1 = Link::fromTextAndUrl('Editar ', $url1);
        $project_link1 = $project_link1->toRenderable();
        $project_link1['#attributes'] = array('class' => array('button'));

        $url = Url::fromRoute('eliminar_imagen.content', array('fid' => $result->fid));
        $project_link = Link::fromTextAndUrl('Eliminar ', $url);
        $project_link = $project_link->toRenderable();
        $project_link['#attributes'] = array('class' => array('button'));

      $rows[$result->fid] = [
//          $result->fid,
          $result->filename,
          Markup::create("<a href='/sites/default/files/content/evidencia/$result->filename'>Imagen</a>"),
          $result->descripcion,
                render($project_link1),
                render($project_link),
      ];

    }

    //Se asignan titulos a cada columna
    $header = [
  //    'id' => t('ID'),
      'name' => t('Nombre'),
      'image' => t('Imagen'),
      'descripcion' => t('Descripcion'),
      'editar' => t('Editar'),
      'eliminar' => t('Eliminar'),
    ];
    //se construye la tabla para mostrar
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => t('Nada para mostrar.'),
    ];

    $form['build'] = [
      '#type' => '#markup',
      '#markup' => render($build),
    ];


    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage(t('The form is working.'));
  }
}
