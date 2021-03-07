<?php
/*
 * @file
 * Contains \Drupal\agregar_imagen\Form\AgregarImagenForm
 */
namespace Drupal\agregar_imagen\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\Link;

use \Drupal\Core\State\StateInterface;

//Para manejo de archivos
use Drupal\file\Entity\File;

class AgregarImagenForm extends FormBase{

  public function getFormId(){
    return 'agregar_imagen_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $rev_id = NULL, $rsh = NULL){
    $form_state->disableCache();

    global $cantidadImg;
    global $id_rev;
    $id_rev = $rev_id;
    global $id_rimg;
    $id_rimg = $rsh;
    //Cantidad de imágenes ya agregadas
    $consulta = Database::getConnection()->select('file_managed', 'f');
    $consulta->addExpression('COUNT(id_rev_sh)','file_managed');
    $consulta->condition('id_rev_sh',$rsh);
    $resultado = $consulta->execute()->fetchCol();
    $cantidadImg = 5 - $resultado[0];
    //Formularios
    for($i = 1; $i <= $cantidadImg; $i++){
      $img = 'img'.$i;
      $description = 'description'.$i;
      $form[$img] =array(
        '#type' => 'managed_file',
        '#title' => 'Evidencia ' . $i,
        '#description' => t('Debe ser formato: jpg, jpeg, png'),
        '#upload_validators' => array(
              //se valida que solo sean archivos de imagen
              'file_validate_extensions' => array('jpg jpeg png'),
              //se limita su tamaño a 100MB
              'file_validate_size' => array(1024 * 1024 * 100),
        ),
        '#upload_location' => 'public://content/evidencia',
      );
      $form[$description] = array(
        '#type' => 'textfield',
        '#title' => 'Descripcion evidencia ' . $i,
        '#size' => 100,
        '#maxlength' => 100,
      );
    }

    //boton submit
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Guardar'),
    );
    $urlR = Url::fromRoute('mostrar_imagen.content', array('rev_id' => $rev_id, 'rsh' => $rsh));
    $project_linkR = Link::fromTextAndUrl('Regresar a imagenes', $urlR);
    $project_linkR = $project_linkR->toRenderable();
    $project_linkR['#attributes'] = array('class' => array('button'));
    $form['regresar'] = array('#markup' => render($project_linkR),);

    return $form;
  }


  public function submitForm(array &$form, FormStateInterface $form_state) {
    // se hace la conexion a la base de datos
    global $cantidadImg;
    global $id_rev;
    global $id_rimg;
    $connection = \Drupal::service('database');
    $contador = 0;
    for($i = 1; $i <= $cantidadImg; $i++){
      $img = 'img'.$i;
      $description = 'description'.$i;
      $form_file = $form_state->getValue([$img]);
      if($form_file){
        $contador++;
        $file = File::load($form_file[0]);
        //se guarda en la base de datos file_managed
        $file->setPermanent();
        $file->save();
        //extraer el nombre del archivo subido
        $file_name = $file->getFilename();
        //se hace update de esa tabla para agregar referencia a la tabla revisiones
        $update = $connection->update('file_managed')
          ->fields(array(
            'id_rev_sh' => $id_rimg,
            'descripcion' => $form_state->getValue([$description]),
          ))
          ->condition('filename', $file_name)
          ->execute();
        }
    }

    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage(t('Imágenes actualizadas.'));
    $form_state->setRedirectUrl(Url::fromRoute('mostrar_imagen.content', array('rev_id' => $id_rev, 'rsh' => $id_rimg)));
  }
}
