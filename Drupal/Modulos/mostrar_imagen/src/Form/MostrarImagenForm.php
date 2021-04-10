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

/*
+ Descripción: Formulario para ver las imágenes relacionadas a un hallazgo de un sitio en una revisión. El pentester selecciona si agregar o borrar imágenes.
*/
class MostrarImagenForm extends FormBase{
  /*
  + Descripción: Función para asignar id del formulario
  + Sin parámetros
  */
  public function getFormId(){
    return 'mostrar_imagen_form';
  }
  /*
  + Descripción: Función para construir el formulario. Se valida al inicio que se tienen permisos para visualizar el formulario.
  + Parámetros:
  +   - $form: arreglo de formulario de Drupal | Tipo: array, Default: NA |
  +   - $form_state: estado de los formularios creados de Drupal | Tipo: FormStateInterface, Default: NA |
  +   - $rev_id: Id de revisión | Tipo: int, Default: NULL |
  +   - $rsh: id_rev_sitio_hall | Tipo: int, Default: NULL |
  +   - $seg: booleano que indica si es revisión de seguimiento | Tipo: bool, Default: 
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
    global $rS;
    $rS = $seg;
    //Consulta de la URL del sitio para imprimirlo
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_hallazgos', 'h');
    $select->join('revisiones_sitios',"s","h.id_rev_sitio = s.id_rev_sitio");
    $select->fields('h', array('id_rev_sitio'));
    $select->condition('id_rev_sitio_hall',$rsh);
    $id_rev_sitio = $select->execute()->fetchCol();

    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->join('sitios',"s","r.id_sitio = s.id_sitio");
    $select->fields('s', array('url_sitio'));
    $select->condition('id_rev_sitio',$id_rev_sitio[0]);
    $activo = $select->execute()->fetchCol();
    $form['sitio'] = array(
      '#type' => 'item',
      '#title' => t('Activo'),
      '#markup' => $activo[0],
    );
    //Mostrar nombre del hallazgo
    $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
    $select->join('hallazgos',"h","h.id_hallazgo = r.id_hallazgo");
    $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
    $select->condition('id_rev_sitio_hall',$rsh);
    $hallazgo = $select->execute()->fetchCol();
    $form['halalzgo'] = array(
      '#type' => 'item',
      '#title' => t('Hallazgo'),
      '#markup' => $hallazgo[0],
    );
    Database::setActiveConnection();
    // se hace la conexion a la base de datos
    $connection = \Drupal::service('database');

    $select = $connection->select('file_managed', 'fm')
        ->fields('fm', array('fid', 'filename', 'descripcion'));
    $select->condition('id_rev_sh', $rsh);
    //Se realiza la consulta
    $results = $select->execute();

    foreach ($results as $result){
      $url = Url::fromRoute('eliminar_imagen.content', array('fid' => $result->fid, 'rev_id' => $rev_id, 'rsh' => $rsh, 'seg' => $seg));
      $project_link = Link::fromTextAndUrl('Eliminar ', $url);
      $project_link = $project_link->toRenderable();
      $project_link['#attributes'] = array('class' => array('button'));

      $rows[$result->fid] = [
          $result->filename,
          Markup::create("<a href='/sites/default/files/content/evidencia/$result->filename'>Imagen</a>"),
          $result->descripcion,
          render($project_link),
      ];

    }

    //Se asignan titulos a cada columna
    $header = [
      'name' => t('Nombre'),
      'image' => t('Imagen'),
      'descripcion' => t('Descripcion'),
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

    
    //Cantidad de imágenes ya agregadas
    $consulta = Database::getConnection()->select('file_managed', 'f');
    $consulta->addExpression('COUNT(id_rev_sh)','file_managed');
    $consulta->condition('id_rev_sh',$rsh);
    $resultado = $consulta->execute()->fetchCol();
    $cantidadImg = 5 - $resultado[0];
    if($cantidadImg){
        $urlA = Url::fromRoute('agregar_imagen.content', array('rev_id' => $rev_id, 'rsh' => $rsh, 'seg' => $seg));
        $project_linkA = Link::fromTextAndUrl('Agregar imágenes', $urlA);
        $project_linkA = $project_linkA->toRenderable();
        $project_linkA['#attributes'] = array('class' => array('button'));
        $form['agregar'] = array('#markup' => render($project_linkA),);
      }

    if(!$seg){
      $urlR = Url::fromRoute('edit_revision.content', array('rev_id' => $rev_id));
    }else{
      $urlR = Url::fromRoute('edit_seguimiento.content', array('rev_id' => $rev_id));
    }
    $project_linkR = Link::fromTextAndUrl('Regresar a revisión', $urlR);
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
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage(t('The form is working.'));
  }
}
