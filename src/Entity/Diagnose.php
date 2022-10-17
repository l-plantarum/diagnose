<? php

namespace Drupal\Diagnose\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupa\diagnose\DiagnoseInterface;

 * @ingroup diagnose
 *
 * @ConfigEntityType(
 *   id = "diagnose",
 *   label = @Translation("Diagnose"),
 *   admin_permission = "administer diagnoses",
 *   handlers = {
 *     "access" = "Drupal\diagnose\DiagnoseAccessController",
 *     "list_builder" = "Drupal\diagnose\Controller\DiagnoseListBuilder",
 *     "form" = {
 *       "add" = "Drupal\diagnose\Form\DiagnoseAddForm",
 *       "edit" = "Drupal\diagnose\Form\DiagnoseEditForm",
 *       "delete" = "Drupal\diagnose\Form\DiagnoseDeleteForm"
 *     }
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *	   "type" = "type",
 *     "file" = "file"
 *   },
 *   links = {
 *     "edit-form" = "/diagnose/manage/{diagnose}",
 *     "delete-form" = "/diagnose/manage/{diagnose}/delete"
 *   },
 *   config_export = {
 *     "id",
 * 	   "type",
 *	   "file"
 *   }
 * )
 */

class Diagnose extends CondigEntityBundleBase {
	  // Diagnose ID
	  public  $id;

	  // diagnose or test
	  public  $type;

	  // XML file name
	  public  $file;
}

