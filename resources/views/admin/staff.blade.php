<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff access · Carolina</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <style>
        .staff-role{display:flex;gap:8px;align-items:center}
        .staff-role select{border:1px solid var(--line);border-radius:6px;padding:8px;background:#fff;font:inherit}
        .staff-role button{border:0;border-radius:6px;padding:8px 10px;background:var(--orange);color:#fff;font-weight:700;cursor:pointer}
        .role-help{color:var(--muted);font-size:12px;margin:8px 0 18px}
    </style>
</head>
<body><div class="admin-shell">
    @include('admin.partials.sidebar')
    <main class="admin-main">
        <header class="admin-topbar"><div>
            <p class="admin-kicker">ACCESS CONTROL</p><h1>Staff roles</h1>
            <p class="admin-subtitle">Give each team member only the tools they need.</p>
        </div></header>
        @include('admin.partials.flash')
        <section class="panel">
            <p class="role-help"><b>Super administrator</b> can grant or remove administrator access. <b>Administrator</b> manages daily operations and non-admin staff roles. <b>Front desk</b> handles bookings and guest service. <b>Housekeeping</b> manages room tasks. <b>Viewer</b> has read-only access. @if (config('auth.staff_mfa_enabled')) Every staff role requires an authenticator at sign-in. @else The authenticator is temporarily paused; staff sign in with a password only. @endif</p>
            <div class="admin-table-wrap"><table>
                <thead><tr><th>Staff member</th><th>Email</th><th>Role</th><th>Save</th></tr></thead>
                <tbody>
                @forelse ($staff as $member)
                    @php
                        $canElevate = \App\Support\AdminPermissions::allows(auth()->user(), 'elevate_staff');
                        $memberRole = \App\Support\AdminPermissions::role($member);
                    @endphp
                    <tr>
                        <td><b>{{ $member->name }}</b></td><td>{{ $member->email }}</td>
                        @if ($member->is_admin && ! $canElevate)
                            <td>{{ $memberRole === 'super_admin' ? 'Super administrator' : 'Administrator' }}</td><td>—</td>
                        @else
                            <td><form id="staff-{{ $member->id }}" class="staff-role" method="POST" action="{{ route('admin.staff.role', $member) }}">
                                @csrf @method('PATCH')
                                <select name="staff_role" aria-label="Role for {{ $member->name }}">
                                    @foreach (($canElevate ? ['super_admin' => 'Super administrator', 'admin' => 'Administrator'] : []) + ['front_desk' => 'Front desk', 'housekeeping' => 'Housekeeping', 'viewer' => 'Viewer', 'guest' => 'Guest'] as $value => $label)
                                        <option value="{{ $value }}" @selected($memberRole === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form></td>
                            <td><button class="staff-role" type="submit" form="staff-{{ $member->id }}">Save</button></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="4" class="no-bookings">No staff accounts are configured yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </main>
</div></body></html>
