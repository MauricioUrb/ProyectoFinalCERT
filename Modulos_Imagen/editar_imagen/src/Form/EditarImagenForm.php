<?php
/*
 * @file
 * Contains \Drupal\editar_imagen\Form\EditarImagenForm
 */
namespace Drupal\editar_imagen\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

//Para manejo de archivos
use Drupal\file\Entity\File;

/*
 *
 */
class EditarImagenForm extends FormBase{
  /*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'editar_imagen_form';
  }
  /*
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state, $fid = NULL ){
    //declaramos una variable global para poder usar en otra funcion
    global $id;
    $id = $fid;

    $form['imagen'] = array(
      '#type' => 'managed_file',
      '#title' => t('Imagen'),
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
    $form['description'] = array(
      '#type' => 'textfield',
      '#title' => t('Descripcion imagen'),
      '#size' => 100,
      '#maxlength' => 100,
      '#required' => TRUE,
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Actualizar'),
    );

    $url = Url::fromRoute('mostrar_imagen.content', array());
    $project_link = Link::fromTextAndUrl('Cancelar', $url);
    $project_link = $project_link->toRenderable();
    $project_link['#attributes'] = array('class' => array('button'));
    $form['cancelar'] = array('#markup' => render($project_link),);

    return $form;

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    global $id;
    //conexion con la db 
    $connection = \Drupal::service('database');
    //se elimina un regitro de la tabla file_managed
    $elimina = $connection->delete('file_managed')
            //se agrega la condicion id_sitio
            ->condition('fid', $id)
            ->execute();

    //se obtiene el valor del form imagen
    $form_file = $form_state->getValue('imagen', 0);
    //se valida que el formulario de archivo no este vacio
    if ($form_file){
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
                    'descripcion' => $form_state->getValue('description'),
                ))
                ->condition('filename', $file_name)
                ->execute();
    }


        // mostramos el mensaje de que se actualizo
        $messenger_service = \Drupal::service('messenger');
        $messenger_service->addMessage(t('Se ha actualizado la base de datos'));
  }
}
