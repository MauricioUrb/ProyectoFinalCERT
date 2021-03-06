<?php
/*
 * @file
 * Contains \Drupal\prueba_imagen\Form\PruebaImagenForm
 */
namespace Drupal\prueba_imagen\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

use \Drupal\Core\State\StateInterface;

//Para manejo de archivos
use Drupal\file\Entity\File;

class PruebaImagenForm extends FormBase{

  public function getFormId(){
    return 'prueba_imagen_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state){
    $form_state->disableCache();

    //aquí se crean los form para subir las imagenes y descripcion
    $form['imagen1'] = array(
      '#type' => 'managed_file',
      '#title' => t('Imagen 1'),
      '#description' => t('Debe ser formato: jpg, jpeg, png'),
      '#upload_validators' => array(
            //se valida que solo sean archivos de imagen
            'file_validate_extensions' => array('jpg jpeg png'),
            //se limita su tamaño a 100MB
            'file_validate_size' => array(1024 * 1024 * 100),
      ),
      '#upload_location' => 'public://content/evidencia',
    );

    //Campo para agregar el pie de pagina (descripcion)
    $form['description1'] = array(
      '#type' => 'textfield',
      '#title' => t('Descripcion imagen 1'),
      '#size' => 100,
      '#maxlength' => 100,
    );

    $form['imagen2'] = array(
      '#type' => 'managed_file',
      '#title' => t('Imagen 2'),
      '#description' => t('Debe ser formato: jpg, jpeg, png'),
      '#upload_validators' => array(
            'file_validate_extensions' => array('jpg jpeg png'),
            'file_validate_size' => array(1024 * 1024 * 100),
      ),
      '#upload_location' => 'public://content/evidencia',
    );

    $form['description2'] = array(
      '#type' => 'textfield',
      '#title' => t('Descripcion imagen 2'),
      '#size' => 100,
      '#maxlength' => 100,
    );

    $form['imagen3'] = array(
      '#type' => 'managed_file',
      '#title' => t('Imagen 3'),
      '#description' => t('Debe ser formato: jpg, jpeg, png'),
      '#upload_validators' => array(
            'file_validate_extensions' => array('jpg jpeg png'),
            'file_validate_size' => array(1024 * 1024 * 100),
      ),
      '#upload_location' => 'public://content/evidencia',
    );

    $form['description3'] = array(
      '#type' => 'textfield',
      '#title' => t('Descripcion imagen 3'),
      '#size' => 100,
      '#maxlength' => 100,
    );

    $form['imagen4'] = array(
      '#type' => 'managed_file',
      '#title' => t('Imagen 4'),
      '#description' => t('Debe ser formato: jpg, jpeg, png'),
      '#upload_validators' => array(
            'file_validate_extensions' => array('jpg jpeg png'),
            'file_validate_size' => array(1024 * 1024 * 100),
      ),
      '#upload_location' => 'public://content/evidencia',
    );

    $form['description4'] = array(
      '#type' => 'textfield',
      '#title' => t('Descripcion imagen 4'),
      '#size' => 100,
      '#maxlength' => 100,
    );

    $form['imagen5'] = array(
      '#type' => 'managed_file',
      '#title' => t('Imagen 5'),
      '#description' => t('Debe ser formato: jpg, jpeg, png'),
      '#upload_validators' => array(
            'file_validate_extensions' => array('jpg jpeg png'),
            'file_validate_size' => array(1024 * 1024 * 100),
      ),
      '#upload_location' => 'public://content/evidencia',
    );

    $form['file5']['description5'] = array(
      '#type' => 'textfield',
      '#title' => t('Descripcion imagen 5'),
      '#size' => 100,
      '#maxlength' => 100,
    );

    //boton submit
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Submit'),
    );

    return $form;
  }


  public function submitForm(array &$form, FormStateInterface $form_state) {
    // se hace la conexion a la base de datos
    $connection = \Drupal::service('database');

    //se obtiene el valor de los form imagen 
    $form_file = $form_state->getValue('imagen1', 0);
    $form_file2 = $form_state->getValue('imagen2', 0);
    $form_file3 = $form_state->getValue('imagen3', 0);
    $form_file4 = $form_state->getValue('imagen4', 0);
    $form_file5 = $form_state->getValue('imagen5', 0);

    //se valida que el formulario de archivo no este vacio
    if ($form_file){
  //if (isset($form_file[0]) && !empty($form_file[0])) {
      $file = File::load($form_file[0]);
      //se guarda en la base de datos file_managed
      $file->setPermanent();
      $file->save();
      //extraer el nombre del archivo subido
      $file_name = $file->getFilename();
      //se hace update de esa tabla para agregar referencia a la tabla revisiones
      $update = $connection->update('file_managed')
              ->fields(array(
                //sustituir el 7 por la variable del id
                  'id_rev_sh' => 7,
                  'descripcion' => $form_state->getValue('description1'),
              ))
              ->condition('filename', $file_name)
              ->execute();
    }

    if ($form_file2){
      $file = File::load($form_file2[0]);
      $file->setPermanent();
      $file->save();
      $file_name = $file->getFilename();
      $update = $connection->update('file_managed')
              ->fields(array(
                  'id_rev_sh' => 7,
                  'descripcion' => $form_state->getValue('description2'),
              ))
              ->condition('filename', $file_name)
              ->execute();
    }

    if ($form_file3){
      $file = File::load($form_file3[0]);
      $file->setPermanent();
      $file->save();
      $file_name = $file->getFilename();
      $update = $connection->update('file_managed')
              ->fields(array(
                  'id_rev_sh' => 7,
                  'descripcion' => $form_state->getValue('description3'),
              ))
              ->condition('filename', $file_name)
              ->execute();
    }

    if ($form_file4){
      $file = File::load($form_file4[0]);
      $file->setPermanent();
      $file->save();
      $file_name = $file->getFilename();
      $update = $connection->update('file_managed')
              ->fields(array(
                  'id_rev_sh' => 7,
                  'descripcion' => $form_state->getValue('description4'),
              ))
              ->condition('filename', $file_name)
              ->execute();
    }

    if ($form_file5){
      $file = File::load($form_file5[0]);
      $file->setPermanent();
      $file->save();
      $file_name = $file->getFilename();
      $update = $connection->update('file_managed')
              ->fields(array(
                  'id_rev_sh' => 7,
                  'descripcion' => $form_state->getValue('description5'),
              ))
              ->condition('filename', $file_name)
              ->execute();
    }

    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage(t('Datos actualizados.'));
  }
}
