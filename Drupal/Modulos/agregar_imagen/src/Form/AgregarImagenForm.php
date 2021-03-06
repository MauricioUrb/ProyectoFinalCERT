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

/*
+ Descripción: Formulario para agregar imágenes (máximo 5) a una revisión.
*/
class AgregarImagenForm extends FormBase{
  /*
  + Descripción: Función para asignar id del formulario
  + Sin parámetros
  */
  public function getFormId(){
    return 'agregar_imagen_form';
  }
  /*
  + Descripción: Función para construir el formulario. Se valida al inicio que se tienen permisos para visualizar el formulario.
  + Parámetros:
  +   - $form: arreglo de formulario de Drupal | Tipo: array, Default: NA |
  +   - $form_state: estado de los formularios creados de Drupal | Tipo: FormStateInterface, Default: NA |
  +   - $rev_id: id de revisión | Tipo: int, Default: NULL |
  +   - $rsh: id_rev_sitio_hall | Tipo: int, Default: NULL |
  +   - $seg: booleano que indica si es revisión de seguimiento | Tipo: bool, Default: NULL |
  */
  public function buildForm(array $form, FormStateInterface $form_state, $rev_id = NULL, $rsh = NULL, $seg = NULL){
    //Comprobación de que el usuario loggeado tiene permiso de ver esta revision
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$rev_id);
    $results = $select->execute()->fetchCol();
    //estatus_revision
    $select = Database::getConnection()->select('actividad', 'a');
    $select->addExpression('MAX(id_estatus)','actividad');
    $select->condition('id_revision',$rev_id);
    $estatus = $select->execute()->fetchCol();
    Database::setActiveConnection();
    if (!in_array(\Drupal::currentUser()->id(), $results) || $estatus[0] > 2){
      return array('#markup' => "No tienes permiso para ver estos formularios.",);
    }
    $form_state->disableCache();

    global $cantidadImg;
    global $id_rev;
    $id_rev = $rev_id;
    global $id_rimg;
    $id_rimg = $rsh;
    global $rS;
    $rS = $seg;
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
    $urlR = Url::fromRoute('mostrar_imagen.content', array('rev_id' => $rev_id, 'rsh' => $rsh, 'seg' => $seg));
    $project_linkR = Link::fromTextAndUrl('Regresar a imagenes', $urlR);
    $project_linkR = $project_linkR->toRenderable();
    $project_linkR['#attributes'] = array('class' => array('button'));
    $form['regresar'] = array('#markup' => render($project_linkR),);

    return $form;
  }
  /*
  + Descripción: Función para mandar los datos proporcionados por el usuario y registrarlos en la base de datos.
  + Parámetros:
  +   - $form: arreglo de formulario de Drupal | Tipo: array, Default: NA |
  +   - $form_state: estado de los formularios creados de Drupal | Tipo: FormStateInterface, Default: NA |
  */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // se hace la conexion a la base de datos
    global $cantidadImg;
    global $id_rev;
    global $id_rimg;
    global $rS;
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
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('actividad', 'a');
    $select->fields('a', array('id_actividad'));
    $select->condition('id_revision', $regresar);
    $select->condition('id_estatus', 2);
    $existe = $select->execute()->fetchCol();
    $tmp = getdate();
    $fecha = $tmp['year'].'-'.$tmp['mon'].'-'.$tmp['mday'];
    $update = $connection->update('actividad')
      ->fields(array(
        'fecha' => $fecha,
      ))
      ->condition('id_actividad',$existe[0])
      ->execute();
    Database::setActiveConnection();
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage(t('Imágenes actualizadas.'));
    $form_state->setRedirectUrl(Url::fromRoute('mostrar_imagen.content', array('rev_id' => $id_rev, 'rsh' => $id_rimg, 'seg' => $rS)));
  }
}
