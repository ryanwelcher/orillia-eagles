<?php
namespace OrillaEagles\Ledger\Admin;

use OrillaEagles\Ledger\Data\PlayerRepository;
use OrillaEagles\Ledger\Data\RosterRepository;

defined( 'ABSPATH' ) || exit;

final class RosterScreen {

	public static function handlePost(): void {
		if ( empty( $_POST['tml_roster_action'] ) ) {
			return;
		}
		if ( ! current_user_can( Menu::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'team-membership-ledger' ) );
		}
		check_admin_referer( 'tml_roster' );

		$repo   = new RosterRepository();
		$action = sanitize_key( wp_unslash( $_POST['tml_roster_action'] ) );

		if ( 'toggle' === $action ) {
			$repo->setActive( absint( $_POST['user_id'] ?? 0 ), ! empty( $_POST['active'] ) );
		} elseif ( 'create' === $action ) {
			try {
				$repo->createMember(
					sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
					sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
					sanitize_email( wp_unslash( $_POST['email'] ?? '' ) )
				);
			} catch ( \RuntimeException $e ) {
				set_transient( 'tml_roster_error', $e->getMessage(), 30 );
			}
		} elseif ( 'link_players' === $action ) {
			( new PlayerRepository() )->setFamilyPlayers(
				absint( $_POST['user_id'] ?? 0 ),
				array_map( 'absint', (array) wp_unslash( $_POST['player_ids'] ?? array() ) )
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . Menu::SLUG ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( Menu::CAP ) ) {
			return;
		}
		$members = ( new RosterRepository() )->allMembers();
		$players = ( new PlayerRepository() )->allPlayers();

		$family_id = absint( $_GET['family'] ?? 0 );
		if ( $family_id ) {
			self::renderFamily( $family_id, $members, $players );
			return;
		}

		$names_by_family = array();
		foreach ( $players as $p ) {
			$names_by_family[ $p['family_id'] ][] = $p['name'];
		}

		$err = get_transient( 'tml_roster_error' );
		delete_transient( 'tml_roster_error' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Families', 'team-membership-ledger' ); ?></h1>
			<?php if ( $err ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $err ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Add family', 'team-membership-ledger' ); ?></h2>
			<p><?php esc_html_e( 'Each family gets a WooCommerce customer account. No email is sent. A self-paying adult is a family of one.', 'team-membership-ledger' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'tml_roster' ); ?>
				<input type="hidden" name="tml_roster_action" value="create" />
				<input type="text" name="first_name" placeholder="<?php esc_attr_e( 'First name', 'team-membership-ledger' ); ?>" required />
				<input type="text" name="last_name" placeholder="<?php esc_attr_e( 'Last name', 'team-membership-ledger' ); ?>" required />
				<input type="email" name="email" placeholder="<?php esc_attr_e( 'Email', 'team-membership-ledger' ); ?>" required />
				<?php submit_button( __( 'Add family', 'team-membership-ledger' ), 'primary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Current families', 'team-membership-ledger' ); ?></h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Name', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Email', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Players', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Active', 'team-membership-ledger' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $members as $m ) : ?>
					<tr>
						<td><?php echo esc_html( $m['name'] ); ?></td>
						<td><?php echo esc_html( $m['email'] ); ?></td>
						<td>
							<?php echo esc_html( implode( ', ', $names_by_family[ $m['id'] ] ?? array() ) ?: '—' ); ?>
							<br /><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::SLUG . '&family=' . $m['id'] ) ); ?>"><?php esc_html_e( 'Edit players', 'team-membership-ledger' ); ?></a>
						</td>
						<td>
							<form method="post" style="margin:0">
								<?php wp_nonce_field( 'tml_roster' ); ?>
								<input type="hidden" name="tml_roster_action" value="toggle" />
								<input type="hidden" name="user_id" value="<?php echo esc_attr( $m['id'] ); ?>" />
								<input type="hidden" name="active" value="<?php echo $m['active'] ? '0' : '1'; ?>" />
								<button class="button">
									<?php echo $m['active'] ? esc_html__( 'Active ✓ (click to deactivate)', 'team-membership-ledger' ) : esc_html__( 'Inactive (click to activate)', 'team-membership-ledger' ); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Checklist of every player; ticked players are paid for by this family.
	 *
	 * @param array $members
	 * @param array $players
	 */
	private static function renderFamily( int $family_id, array $members, array $players ): void {
		$names = array_column( $members, 'name', 'id' );
		if ( ! isset( $names[ $family_id ] ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Family not found.', 'team-membership-ledger' ) . '</p></div>';
			return;
		}
		?>
		<div class="wrap">
			<h1>
				<?php
				/* translators: %s: family name. */
				echo esc_html( sprintf( __( 'Players paid for by %s', 'team-membership-ledger' ), $names[ $family_id ] ) );
				?>
			</h1>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::SLUG ) ); ?>">&larr; <?php esc_html_e( 'All families', 'team-membership-ledger' ); ?></a></p>
			<form method="post">
				<?php wp_nonce_field( 'tml_roster' ); ?>
				<input type="hidden" name="tml_roster_action" value="link_players" />
				<input type="hidden" name="user_id" value="<?php echo esc_attr( $family_id ); ?>" />
				<fieldset>
				<?php foreach ( $players as $p ) : ?>
					<label style="display:block;margin:4px 0">
						<input type="checkbox" name="player_ids[]" value="<?php echo esc_attr( $p['id'] ); ?>" <?php checked( $p['family_id'], $family_id ); ?> />
						<?php echo esc_html( $p['name'] ); ?>
						<?php if ( $p['family_id'] && $p['family_id'] !== $family_id ) : ?>
							<em>
								<?php
								/* translators: %s: family name. */
								echo esc_html( sprintf( __( '(currently: %s)', 'team-membership-ledger' ), $names[ $p['family_id'] ] ?? '?' ) );
								?>
							</em>
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
				</fieldset>
				<?php submit_button( __( 'Save players', 'team-membership-ledger' ) ); ?>
			</form>
		</div>
		<?php
	}
}
