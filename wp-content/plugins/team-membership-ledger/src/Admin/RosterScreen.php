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
		} elseif ( 'link_player' === $action ) {
			try {
				( new PlayerRepository() )->link( absint( $_POST['player_id'] ?? 0 ), absint( $_POST['user_id'] ?? 0 ) );
			} catch ( \RuntimeException $e ) {
				set_transient( 'tml_roster_error', $e->getMessage(), 30 );
			}
		} elseif ( 'unlink_player' === $action ) {
			try {
				( new PlayerRepository() )->unlink( absint( $_POST['player_id'] ?? 0 ) );
			} catch ( \RuntimeException $e ) {
				set_transient( 'tml_roster_error', $e->getMessage(), 30 );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . Menu::SLUG ) );
		exit;
	}

	/**
	 * The screen has a form per row, and wp_nonce_field() gives every one the same
	 * id="_wpnonce". Same field, without the repeated id.
	 */
	private static function nonce_field(): void {
		printf( '<input type="hidden" name="_wpnonce" value="%s" />', esc_attr( wp_create_nonce( 'tml_roster' ) ) );
	}

	public static function render(): void {
		if ( ! current_user_can( Menu::CAP ) ) {
			return;
		}
		$members    = ( new RosterRepository() )->allMembers();
		$member_ids = array_flip( wp_list_pluck( $members, 'id' ) );
		$linked     = array();
		$unlinked   = array();
		// Linked to a user who was deleted or is no longer a customer. They have no
		// row to show under, so list them on their own or they can never be relinked.
		$orphaned = array();
		foreach ( ( new PlayerRepository() )->allPlayers() as $p ) {
			if ( ! $p['member_id'] ) {
				$unlinked[] = $p;
			} elseif ( isset( $member_ids[ $p['member_id'] ] ) ) {
				$linked[ $p['member_id'] ][] = $p;
			} else {
				$orphaned[] = $p;
			}
		}
		$err     = get_transient( 'tml_roster_error' );
		delete_transient( 'tml_roster_error' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Members', 'team-membership-ledger' ); ?></h1>
			<?php if ( $err ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $err ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Add member', 'team-membership-ledger' ); ?></h2>
			<form method="post">
				<?php self::nonce_field(); ?>
				<input type="hidden" name="tml_roster_action" value="create" />
				<input type="text" name="first_name" placeholder="<?php esc_attr_e( 'First name', 'team-membership-ledger' ); ?>" required />
				<input type="text" name="last_name" placeholder="<?php esc_attr_e( 'Last name', 'team-membership-ledger' ); ?>" required />
				<input type="email" name="email" placeholder="<?php esc_attr_e( 'Email', 'team-membership-ledger' ); ?>" required />
				<?php submit_button( __( 'Add member', 'team-membership-ledger' ), 'primary', 'submit', false ); ?>
			</form>

			<?php if ( $orphaned ) : ?>
				<h2><?php esc_html_e( 'Players linked to a missing member', 'team-membership-ledger' ); ?></h2>
				<p><?php esc_html_e( 'These players are linked to a user who was deleted or is no longer a member, so they are not being charged. Unlink them, then link them to a current member.', 'team-membership-ledger' ); ?></p>
				<?php foreach ( $orphaned as $p ) : ?>
					<form method="post" style="margin:0 0 4px">
						<?php self::nonce_field(); ?>
						<input type="hidden" name="tml_roster_action" value="unlink_player" />
						<input type="hidden" name="player_id" value="<?php echo esc_attr( $p['id'] ); ?>" />
						<?php echo esc_html( $p['name'] ); ?>
						<?php /* translators: %s: player name */ ?>
						<button class="button-link" aria-label="<?php echo esc_attr( sprintf( __( 'Unlink %s', 'team-membership-ledger' ), $p['name'] ) ); ?>">
							<?php esc_html_e( 'Unlink', 'team-membership-ledger' ); ?>
						</button>
					</form>
				<?php endforeach; ?>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Current members', 'team-membership-ledger' ); ?></h2>
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
							<?php foreach ( $linked[ $m['id'] ] ?? array() as $p ) : ?>
								<form method="post" style="margin:0 0 4px">
									<?php self::nonce_field(); ?>
									<input type="hidden" name="tml_roster_action" value="unlink_player" />
									<input type="hidden" name="player_id" value="<?php echo esc_attr( $p['id'] ); ?>" />
									<?php echo esc_html( $p['name'] ); ?>
									<?php /* translators: %s: player name */ ?>
									<button class="button-link" aria-label="<?php echo esc_attr( sprintf( __( 'Unlink %s', 'team-membership-ledger' ), $p['name'] ) ); ?>">
										<?php esc_html_e( 'Unlink', 'team-membership-ledger' ); ?>
									</button>
								</form>
							<?php endforeach; ?>
							<?php if ( $unlinked ) : ?>
								<form method="post" style="margin:0">
									<?php self::nonce_field(); ?>
									<input type="hidden" name="tml_roster_action" value="link_player" />
									<input type="hidden" name="user_id" value="<?php echo esc_attr( $m['id'] ); ?>" />
									<label class="screen-reader-text" for="tml-link-player-<?php echo esc_attr( $m['id'] ); ?>"><?php esc_html_e( 'Player to link', 'team-membership-ledger' ); ?></label>
									<select name="player_id" id="tml-link-player-<?php echo esc_attr( $m['id'] ); ?>" required>
										<option value=""><?php esc_html_e( '— Link a player —', 'team-membership-ledger' ); ?></option>
										<?php foreach ( $unlinked as $p ) : ?>
											<option value="<?php echo esc_attr( $p['id'] ); ?>"><?php echo esc_html( $p['name'] ); ?></option>
										<?php endforeach; ?>
									</select>
									<button class="button"><?php esc_html_e( 'Link', 'team-membership-ledger' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
						<td>
							<form method="post" style="margin:0">
								<?php self::nonce_field(); ?>
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
}
