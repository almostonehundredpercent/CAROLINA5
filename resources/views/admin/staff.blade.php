<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff access · Carolina</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <style>
        .role-help{color:#d8c6b7;font-size:14px;line-height:1.65;margin:8px 0 20px}
        .staff-access-table{min-width:760px}
        .staff-access-table td{vertical-align:middle}
        .staff-role-form{width:100%;min-width:180px}
        .staff-role-picker{position:relative;display:grid;gap:8px;width:100%;color:#fff6ec}
        .staff-role-picker select{width:100%;min-height:46px;border:1px solid #765439;border-radius:12px;padding:10px 42px 10px 13px;background:#211812;color:#fff6ec;font:inherit;font-size:14px;font-weight:600;line-height:1.3;color-scheme:dark}
        .staff-role-picker select:focus-visible,.staff-role-trigger:focus-visible,.staff-role-option:focus-visible,.staff-role-save:focus-visible{outline:3px solid #f0a528;outline-offset:2px}
        .staff-role-trigger{display:none;width:100%;min-height:46px;align-items:center;justify-content:space-between;gap:12px;border:1px solid #765439;border-radius:12px;padding:10px 13px;background:linear-gradient(145deg,#39271b,#241913);color:#fff6ec;text-align:left;font:inherit;font-size:14px;font-weight:600;line-height:1.3;cursor:pointer}
        .staff-role-trigger:hover{border-color:#d48b22;background:linear-gradient(145deg,#49301e,#2c1d14)}
        .staff-role-picker[data-enhanced="true"] select{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}
        .staff-role-picker[data-enhanced="true"] .staff-role-trigger{display:flex}
        .staff-role-options{display:grid;gap:4px;padding:6px;border:1px solid #765439;border-radius:12px;background:#1b1410;box-shadow:0 12px 26px rgba(0,0,0,.3)}
        .staff-role-options[hidden]{display:none!important}
        .staff-role-option{width:100%;min-height:42px;border:1px solid transparent;border-radius:8px;padding:9px 11px;background:transparent;color:#e9d9c9;text-align:left;font:inherit;font-size:14px;font-weight:500;line-height:1.3;cursor:pointer}
        .staff-role-option:hover,.staff-role-option[aria-selected="true"]{border-color:#8d5a26;background:#3b281b;color:#fff7ec}
        .staff-role-option[aria-selected="true"]:after{content:"Selected";float:right;color:#ffc66b;font-size:11px;font-weight:700}
        .staff-role-save{min-height:44px;min-width:76px;border:1px solid #e89400;border-radius:11px;padding:9px 14px;background:linear-gradient(135deg,#ed9d13,#c66c00);color:#201309;font:inherit;font-size:14px;font-weight:700;line-height:1.2;cursor:pointer;box-shadow:0 4px 10px rgba(201,111,0,.18)}
        .staff-role-save:hover{filter:brightness(1.08);transform:translateY(-1px)}
        .staff-role-save:active{transform:translateY(0)}
        .staff-role-static{color:#fff1e3;font-weight:600}
        html:not(.dark-mode) .role-help{color:#65564b}
        html:not(.dark-mode) .staff-role-picker{color:#2b211b}
        html:not(.dark-mode) .staff-role-picker select{border-color:#dfd1c3;background:#fffdf9;color:#2b211b;color-scheme:light}
        html:not(.dark-mode) .staff-role-trigger{border-color:#dfd1c3;background:linear-gradient(145deg,#fffdf9,#f7efe6);color:#2b211b}
        html:not(.dark-mode) .staff-role-trigger:hover{border-color:#c66b00;background:linear-gradient(145deg,#fffaf3,#f7eadb)}
        html:not(.dark-mode) .staff-role-options{border-color:#dfd1c3;background:#fffdf9;box-shadow:0 12px 26px rgba(63,38,20,.12)}
        html:not(.dark-mode) .staff-role-option{color:#4a3b31}
        html:not(.dark-mode) .staff-role-option:hover,html:not(.dark-mode) .staff-role-option[aria-selected="true"]{border-color:#e3c8a8;background:#fff0dd;color:#442b18}
        html:not(.dark-mode) .staff-role-option[aria-selected="true"]:after{color:#9b5100}
        html:not(.dark-mode) .staff-role-static{color:#2b211b}
        @media(max-width:700px){
            .admin-table-wrap .staff-access-table{min-width:0;width:100%;border-collapse:separate;border-spacing:0 12px}
            .staff-access-table thead{display:none}
            .admin-table-wrap:has(.staff-access-table){overflow:visible}
            .staff-access-table tbody{display:grid;gap:2px}
            .staff-access-table tbody tr{display:grid;grid-template-columns:minmax(0,1fr) auto;grid-template-areas:"member member" "email email" "role save";gap:9px 12px;margin-bottom:10px;padding:15px;border:1px solid #684a33;border-radius:14px;background:linear-gradient(145deg,rgba(54,37,26,.9),rgba(30,22,17,.96));box-shadow:0 8px 20px rgba(0,0,0,.16)}
            .staff-access-table tbody td{display:block;min-width:0;padding:0!important;border:0!important;white-space:normal}
            .staff-access-table td[data-label="Staff member"]{grid-area:member;color:#fff7ed;font-size:16px}
            .staff-access-table td[data-label="Email"]{grid-area:email;margin-top:-5px;color:#d3bdab;font-size:13px;overflow-wrap:anywhere}
            .staff-access-table td[data-label="Role"]{grid-area:role;align-self:end}
            .staff-access-table td[data-label="Save"]{grid-area:save;align-self:end}
            .staff-access-table td[data-label="Role"]:before{display:block;margin:0 0 6px 2px;color:#d4ae7e;content:"ROLE";font-size:10px;font-weight:700;letter-spacing:.08em}
            .staff-role-form{min-width:0}
            .staff-role-save{min-height:46px;padding-inline:16px}
            .staff-access-table .no-bookings{grid-column:1/-1;text-align:center}
        }
        @media(max-width:700px){
            html:not(.dark-mode) .staff-access-table tbody tr{border-color:#e5ddd4;background:linear-gradient(145deg,#fffdf9,#f7efe6);box-shadow:0 8px 20px rgba(63,38,20,.06)}
            html:not(.dark-mode) .staff-access-table td[data-label="Staff member"]{color:#2b211b}
            html:not(.dark-mode) .staff-access-table td[data-label="Email"]{color:#74685e}
            html:not(.dark-mode) .staff-access-table td[data-label="Role"]:before{color:#8d4b0b}
        }
    </style>
    <script src="{{ asset('js/staff-role-picker.js') }}?v={{ filemtime(public_path('js/staff-role-picker.js')) }}" defer></script>
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
            <div class="admin-table-wrap"><table class="staff-access-table">
                <thead><tr><th>Staff member</th><th>Email</th><th>Role</th><th>Save</th></tr></thead>
                <tbody>
                @forelse ($staff as $member)
                    @php
                        $canElevate = \App\Support\AdminPermissions::allows(auth()->user(), 'elevate_staff');
                        $memberRole = \App\Support\AdminPermissions::role($member);
                        $roleLabels = ['super_admin' => 'Super administrator', 'admin' => 'Administrator', 'front_desk' => 'Front desk', 'housekeeping' => 'Housekeeping', 'viewer' => 'Viewer', 'guest' => 'Guest'];
                        $availableRoles = ($canElevate ? ['super_admin' => $roleLabels['super_admin'], 'admin' => $roleLabels['admin']] : []) + ['front_desk' => $roleLabels['front_desk'], 'housekeeping' => $roleLabels['housekeeping'], 'viewer' => $roleLabels['viewer'], 'guest' => $roleLabels['guest']];
                        $memberRoleLabel = $roleLabels[$memberRole] ?? 'Guest';
                    @endphp
                    <tr>
                        <td data-label="Staff member"><b>{{ $member->name }}</b></td><td data-label="Email">{{ $member->email }}</td>
                        @if ($member->is_admin && ! $canElevate)
                            <td data-label="Role" class="staff-role-static">{{ $memberRoleLabel }}</td><td data-label="Save">—</td>
                        @else
                            <td data-label="Role"><form id="staff-{{ $member->id }}" class="staff-role-form" method="POST" action="{{ route('admin.staff.role', $member) }}">
                                @csrf @method('PATCH')
                                <div class="staff-role-picker" data-staff-role-picker>
                                    <select name="staff_role" aria-label="Role for {{ $member->name }}">
                                        @foreach ($availableRoles as $value => $label)
                                            <option value="{{ $value }}" @selected($memberRole === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="staff-role-trigger" data-staff-role-trigger aria-haspopup="listbox" aria-expanded="false" aria-label="Change role for {{ $member->name }}">
                                        <span data-staff-role-label>{{ $memberRoleLabel }}</span>
                                        <span aria-hidden="true">⌄</span>
                                    </button>
                                    <div class="staff-role-options" data-staff-role-options role="listbox" aria-label="Choose a staff role" hidden>
                                        @foreach ($availableRoles as $value => $label)
                                            <button type="button" class="staff-role-option" role="option" data-value="{{ $value }}" aria-selected="{{ $memberRole === $value ? 'true' : 'false' }}">{{ $label }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            </form></td>
                            <td data-label="Save"><button class="staff-role-save" type="submit" form="staff-{{ $member->id }}">Save</button></td>
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
