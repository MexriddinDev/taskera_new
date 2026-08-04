import React, { useState, useEffect } from 'react';
import {
  ShieldCheck,
  Building,
  UserCheck,
  Key,
  Plus,
  Trash2,
  Check,
  AlertCircle,
  Search,
  Users,
  UsersRound,
  RefreshCw,
  FolderTree,
  UserPlus,
  Pencil,
  Info
} from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';

interface Role {
  id: number;
  name: string;
  guard_name: string;
  description?: string;
  users_count?: number;
  permission_ids?: number[];
  permissions?: Permission[];
}

interface Permission {
  id: number;
  name: string;
  guard_name: string;
  module: string;
  description?: string;
}

interface Department {
  id: number;
  name: string;
  code: string;
  branch_id?: number | null;
  manager_employee_id?: number | null;
  cost_center?: string;
  is_active: boolean;
}

interface Branch {
  id: number;
  name: string;
  code: string;
}

interface Position {
  id: number;
  name: string;
  code: string;
}

interface Team {
  id: number;
  name: string;
  code: string;
  department_id?: number | null;
  manager_user_id?: number | null;
  is_active: boolean;
  members_count?: number;
}

interface UserWithRole {
  id: number;
  employeeId?: number | null;
  username: string;
  email: string;
  name: string;
  departmentId?: number | null;
  departmentName?: string;
  branchId?: number | null;
  branchName?: string;
  positionId?: number | null;
  positionName?: string;
  roleId?: number | null;
  roleName?: string;
  permissions?: string[];
  teamIds?: number[];
  teams?: { id: number; name: string; code?: string }[];
}

const PERMISSION_FRIENDLY_INFO: Record<string, { label: string; desc: string; icon: string }> = {
  'dashboard.view': { label: 'Dashboard Paneli', desc: 'Dashboard boshqaruv va statistika paneliga kirish', icon: '📌' },
  'tasks.view': { label: 'Barcha Topshiriqlar', desc: 'Barcha murojaatlar va zayavkalar ro\'yxatini ko\'rish', icon: '📋' },
  'my_tasks.view': { label: 'Mening Topshiriqlarim', desc: 'Faqat o\'ziga biriktirilgan zayavkalarni ko\'rish', icon: '✍️' },
  'monitoring.view': { label: 'TV Monitoring (Command Center)', desc: 'Katta monitor TV operatsiyalar paneliga kirish', icon: '📺' },
  'team_workload.view': { label: 'Xodimlar Zayavkalari', desc: 'Guruh xodimlarining zayavkalari va ish yuklamasi', icon: '👥' },
  'stats.view': { label: 'Statistika', desc: 'Barcha unumdorlik va ijro intizomi statistikasi', icon: '📊' },
  'roles.manage': { label: 'Rollar & Bo\'limlar (RBAC)', desc: 'Foydalanuvchilar, rollar va huquqlarni boshqarish', icon: '🛡️' },

  'tickets.view': { label: 'Zayavkalarni Ko\'rish', desc: 'Barcha zayavka ma\'lumotlarini o\'qish va ko\'rish', icon: '👁️' },
  'tickets.create': { label: 'Yangi Zayavka Yaratish', desc: 'Yangi murojaat va topshiriq biriktirish', icon: '➕' },
  'tickets.assign': { label: 'Xodimlarga Biriktirish', desc: 'Zayavkani ijrochi xodimga yo\'naltirish', icon: '👤' },
  'tickets.transition': { label: 'Holatni O\'zgartirish', desc: 'Zayavkani bajarish, rad etish va yopish', icon: '🔄' },
  'tickets.view_own': { label: 'Faqat O\'z Zayavkalari', desc: 'Faqat o\'zi ochgan yoki biriktirilgan zayavkani ko\'rish', icon: '🔒' },
  'tickets.export': { label: 'Eksport Qilish', desc: 'Zayavkalarni Excel yoki PDF ga yuklab olish', icon: '📥' },
  'tickets.delete': { label: 'Zayavkani O\'chirish', desc: 'Murojaatlarni tizimdan o\'chirish', icon: '❌' },

  'users.manage': { label: 'Xodimlarni Boshqarish', desc: 'Foydalanuvchi akkauntlarini boshqarish', icon: '👨‍💼' },
  'departments.manage': { label: 'Bo\'limlar & Guruhlar', desc: 'Tashkilot va xizmat guruhlarini sozlash', icon: '🏢' },
  'knowledge.view': { label: 'Bilimlar Bazasi', desc: 'Qo\'llanmalar va yo\'riqnomalarni ko\'rish', icon: '💡' },
  'knowledge.manage': { label: 'Bilimlar Bazasini Boshqarish', desc: 'Yangi yo\'riqnomalar yaratish va nashr etish', icon: '✏️' },
  'assets.view': { label: 'IT Aktivlarni Ko\'rish', desc: 'Kompyuterlar va uskunalar ro\'yxatini ko\'rish', icon: '💻' },
  'assets.manage': { label: 'IT Aktivlarni Boshqarish', desc: 'Uskunalarni ro\'yxatdan o\'tkazish va biriktirish', icon: '⚙️' },
  'sla.manage': { label: 'SLA Sozlamalari', desc: 'Xizmat muddati (SLA) va ish vaqtini sozlash', icon: '⏱️' },
  'audit.view': { label: 'Audit Loglari', desc: 'Tizim amallari va xavfsizlik loglarini ko\'rish', icon: '📜' },
};

const MODULE_NAMES: Record<string, string> = {
  NAVBAR: '🖥️ NAVBAR & SAHIFALARGA KIRISH',
  TICKETS: '🎫 ZAYAVKALAR VA OPERATSIYALAR',
  RBAC: '🛡️ ROLLAR VA XAVFSIZLIK',
  ORG: '🏢 TASHKILOT VA BO\'LIMLAR',
  ANALYTICS: '📊 ANALITIKA & MONITORING',
  KNOWLEDGE: '💡 BILIMLAR BAZASI',
  CMDB: '💻 IT AKTIVLAR & USKUNALAR',
  SLA: '⏱️ SLA VA VAQT',
  SECURITY: '📜 AUDIT VA LOGLAR',
};

const GroupedPermissionSelector: React.FC<{
  permissions: Permission[];
  selectedIds: number[];
  onToggle: (id: number) => void;
  onSelectGroup?: (ids: number[], select: boolean) => void;
}> = ({ permissions, selectedIds, onToggle, onSelectGroup }) => {
  const grouped = React.useMemo(() => {
    const map: Record<string, Permission[]> = {};
    permissions.forEach((p) => {
      const mod = p.module || 'CORE';
      if (!map[mod]) map[mod] = [];
      map[mod].push(p);
    });
    return map;
  }, [permissions]);

  return (
    <div className="max-h-80 overflow-y-auto space-y-4 p-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60">
      {Object.entries(grouped).map(([mod, perms]) => {
        const modTitle = MODULE_NAMES[mod] || `📂 ${mod}`;
        const allSelected = perms.every((p) => selectedIds.includes(p.id));

        return (
          <div key={mod} className="space-y-2 border-b border-slate-200/60 dark:border-slate-800/80 pb-3 last:border-0 last:pb-0">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-black uppercase tracking-wider text-purple-600 dark:text-purple-400">
                {modTitle}
              </span>
              <button
                type="button"
                onClick={() => {
                  if (onSelectGroup) {
                    onSelectGroup(perms.map((p) => p.id), !allSelected);
                  }
                }}
                className="text-[10px] font-bold text-slate-500 hover:text-purple-600 dark:hover:text-purple-300 transition-colors"
              >
                {allSelected ? '✓ Barchasini yechish' : '+ Barchasini tanlash'}
              </button>
            </div>

            <div className="grid grid-cols-1 gap-2">
              {perms.map((p) => {
                const isChecked = selectedIds.includes(p.id);
                const info = PERMISSION_FRIENDLY_INFO[p.name] || {
                  label: p.name,
                  desc: p.description || 'Tizim huquqi',
                  icon: '🔹',
                };

                return (
                  <label
                    key={p.id}
                    className={`flex items-start space-x-2.5 p-2.5 rounded-xl border transition-all cursor-pointer ${
                      isChecked
                        ? 'bg-purple-50/80 dark:bg-purple-950/40 border-purple-300 dark:border-purple-800/80 text-purple-950 dark:text-purple-100 shadow-2xs'
                        : 'bg-white dark:bg-slate-800/80 border-slate-200 dark:border-slate-700/80 text-slate-700 dark:text-slate-300 hover:border-slate-300'
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={isChecked}
                      onChange={() => onToggle(p.id)}
                      className="mt-0.5 rounded text-purple-600 focus:ring-purple-500"
                    />
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between gap-1">
                        <span className="text-xs font-bold flex items-center space-x-1">
                          <span>{info.icon}</span>
                          <span>{info.label}</span>
                        </span>
                        <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700/80 text-slate-500 dark:text-slate-400">
                          {p.name}
                        </span>
                      </div>
                      <p className="text-[11px] text-slate-500 dark:text-slate-400 font-medium leading-tight mt-0.5">
                        {info.desc}
                      </p>
                    </div>
                  </label>
                );
              })}
            </div>
          </div>
        );
      })}
    </div>
  );
};

export const RbacManagementPage: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'departments' | 'roles' | 'permissions' | 'teams' | 'assignments'>('departments');

  // Master Data States
  const [roles, setRoles] = useState<Role[]>([]);
  const [permissions, setPermissions] = useState<Permission[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [positions, setPositions] = useState<Position[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [users, setUsers] = useState<UserWithRole[]>([]);

  const [loading, setLoading] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Department Form State
  const [deptName, setDeptName] = useState('');
  const [deptCode, setDeptCode] = useState('');
  const [deptBranchId, setDeptBranchId] = useState<number | null>(null);

  // Role Form State
  const [roleName, setRoleName] = useState('');
  const [roleGuard, setRoleGuard] = useState('web');
  const [roleDesc, setRoleDesc] = useState('');
  const [rolePermIds, setRolePermIds] = useState<number[]>([]);

  // Permission Form State
  const [permName, setPermName] = useState('');
  const [permGuard, setPermGuard] = useState('web');
  const [permModule, setPermModule] = useState('CORE');
  const [permDesc, setPermDesc] = useState('');

  // Team / Group Form State
  const [teamName, setTeamName] = useState('');
  const [teamCode, setTeamCode] = useState('');
  const [teamDeptId, setTeamDeptId] = useState<number | null>(null);
  const [selectedTeamId, setSelectedTeamId] = useState<number | null>(null);
  const [addTeamMemberUserId, setAddTeamMemberUserId] = useState<number | null>(null);

  // Assignment Form State
  const [selectedUserId, setSelectedUserId] = useState<number | null>(null);
  const [selectedRoleId, setSelectedRoleId] = useState<number | null>(null);
  const [selectedDeptId, setSelectedDeptId] = useState<number | null>(null);
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);
  const [selectedPosId, setSelectedPosId] = useState<number | null>(null);
  const [selectedPermIds, setSelectedPermIds] = useState<number[]>([]);
  const [selectedTeamIds, setSelectedTeamIds] = useState<number[]>([]);

  // Edit Modal States
  const [editingRole, setEditingRole] = useState<Role | null>(null);
  const [editRoleName, setEditRoleName] = useState('');
  const [editRoleGuard, setEditRoleGuard] = useState('web');
  const [editRoleDesc, setEditRoleDesc] = useState('');
  const [editRolePermIds, setEditRolePermIds] = useState<number[]>([]);

  const [editingPermission, setEditingPermission] = useState<Permission | null>(null);
  const [editPermName, setEditPermName] = useState('');
  const [editPermGuard, setEditPermGuard] = useState('web');
  const [editPermModule, setEditPermModule] = useState('CORE');
  const [editPermDesc, setEditPermDesc] = useState('');

  const [editingDepartment, setEditingDepartment] = useState<Department | null>(null);
  const [editDeptName, setEditDeptName] = useState('');
  const [editDeptBranchId, setEditDeptBranchId] = useState<number | null>(null);

  const [editingTeam, setEditingTeam] = useState<Team | null>(null);
  const [editTeamName, setEditTeamName] = useState('');
  const [editTeamDeptId, setEditTeamDeptId] = useState<number | null>(null);

  // Members Modal State (shown when clicking "X kishi")
  const [membersModal, setMembersModal] = useState<{ title: string; members: { id: number; name: string; username?: string }[] } | null>(null);
  const [membersLoading, setMembersLoading] = useState(false);

  // Filters & Combobox
  const [permSearch, setPermSearch] = useState('');
  const [permModuleFilter, setPermModuleFilter] = useState<string>('ALL');
  const [userSearch, setUserSearch] = useState('');
  const [empSearchQuery, setEmpSearchQuery] = useState('');
  const [empComboboxOpen, setEmpComboboxOpen] = useState(false);

  const fetchAllData = async () => {
    setLoading(true);
    setError(null);
    try {
      const [rolesRes, permsRes, usersRes, deptsRes, branchesRes, positionsRes, teamsRes] = await Promise.all([
        axiosClient.get<{ data: Role[] }>('/roles'),
        axiosClient.get<{ data: Permission[] }>('/permissions'),
        axiosClient.get<{ data: UserWithRole[] }>('/users/roles'),
        axiosClient.get<{ data: Department[] }>('/departments').catch(() => ({ data: { data: [] } })),
        axiosClient.get<{ data: Branch[] }>('/branches').catch(() => ({ data: { data: [] } })),
        axiosClient.get<{ data: Position[] }>('/positions').catch(() => ({ data: { data: [] } })),
        axiosClient.get<{ data: Team[] }>('/teams').catch(() => ({ data: { data: [] } })),
      ]);

      setRoles(rolesRes.data.data || []);
      setPermissions(permsRes.data.data || []);
      setUsers(usersRes.data.data || []);
      setDepartments(deptsRes.data.data || []);
      setBranches(branchesRes.data.data || []);
      setPositions(positionsRes.data.data || []);
      setTeams(teamsRes.data.data || []);

      const userList = usersRes.data.data || [];
      if (userList.length > 0 && !selectedUserId) {
        const firstUser = userList[0];
        setSelectedUserId(firstUser.id);
        setSelectedRoleId(firstUser.roleId ?? 0);
        setSelectedDeptId(firstUser.departmentId || null);
        setSelectedBranchId(firstUser.branchId || null);
        setSelectedPosId(firstUser.positionId || null);
      }
    } catch (err: any) {
      setError(err.message || 'Ma\'lumotlarni yuklashda xatolik yuz berdi');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAllData();
  }, []);

  useEffect(() => {
    if (selectedUserId) {
      const u = users.find((item) => item.id === selectedUserId);
      if (u) {
        setSelectedRoleId(u.roleId ?? 0);
        setSelectedDeptId(u.departmentId || null);
        setSelectedBranchId(u.branchId || null);
        setSelectedPosId(u.positionId || null);

        const userPerms = u.permissions || [];
        const matchedPermIds = permissions
          .filter((p) => userPerms.includes(p.name))
          .map((p) => p.id);
        setSelectedPermIds(matchedPermIds);

        const tIds = u.teamIds || u.teams?.map((t: any) => t.id) || [];
        setSelectedTeamIds(tIds);
      }
    }
  }, [selectedUserId, users]);

  const showNotification = (msg: string) => {
    setMessage(msg);
    setTimeout(() => setMessage(null), 4000);
  };

  // Handlers
  const handleCreateDepartment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!deptName.trim()) return;

    setActionLoading(true);
    setError(null);
    try {
      await axiosClient.post('/departments', {
        name: deptName,
        branch_id: deptBranchId,
        organization_id: 1,
      });

      setDeptName('');
      setDeptCode('');
      setDeptBranchId(null);
      showNotification('Yangi bo\'lim muvaffaqiyatli yaratildi!');
      fetchAllData();
    } catch (err: any) {
      setError(err.message || 'Bo\'lim yaratishda xatolik yuz berdi');
    } finally {
      setActionLoading(false);
    }
  };

  const handleDeleteDepartment = async (id: number) => {
    if (!window.confirm('Haqiqatdan ham ushbu bo\'limni o\'chirmoqchimisiz?')) return;
    setActionLoading(true);
    try {
      await axiosClient.delete(`/departments/${id}`);
      showNotification('Bo\'lim o\'chirildi');
      fetchAllData();
    } catch (err: any) {
      setError(err.message || 'Bo\'limni o\'chirishda xatolik');
    } finally {
      setActionLoading(false);
    }
  };

  const handleCreateRole = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!roleName.trim()) return;

    setActionLoading(true);
    setError(null);
    try {
      await axiosClient.post('/roles', {
        name: roleName,
        guard_name: roleGuard || 'web',
        description: roleDesc || undefined,
        permissions: rolePermIds,
      });

      setRoleName('');
      setRoleDesc('');
      setRolePermIds([]);
      showNotification('Yangi rol va biriktirilgan huquqlar muvaffaqiyatli yaratildi!');
      fetchAllData();
    } catch (err: any) {
      setError(err.message || 'Rol yaratishda xatolik yuz berdi');
    } finally {
      setActionLoading(false);
    }
  };

  const handleDeleteRole = async (id: number) => {
    if (!window.confirm('Haqiqatdan ham ushbu rolni o\'chirmoqchimisiz?')) return;
    setActionLoading(true);
    try {
      await axiosClient.delete(`/roles/${id}`);
      showNotification('Rol o\'chirildi');
      fetchAllData();
    } catch (err: any) {
      setError(err.message || 'Rolni o\'chirishda xatolik');
    } finally {
      setActionLoading(false);
    }
  };

  const handleCreatePermission = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!permName.trim()) return;

    setActionLoading(true);
    setError(null);
    try {
      await axiosClient.post('/permissions', {
        name: permName,
        guard_name: permGuard || 'web',
        module: permModule || 'CORE',
        description: permDesc || undefined,
      });

      setPermName('');
      setPermDesc('');
      showNotification('Yangi permission muvaffaqiyatli yaratildi!');
      fetchAllData();
    } catch (err: any) {
      setError(err.message || 'Permission yaratishda xatolik yuz berdi');
    } finally {
      setActionLoading(false);
    }
  };

  const handleDeletePermission = async (id: number) => {
    if (!window.confirm('Haqiqatdan ham ushbu huquqni o\'chirmoqchimisiz?')) return;
    setActionLoading(true);
    try {
      await axiosClient.delete(`/permissions/${id}`);
      showNotification('Permission o\'chirildi');
      fetchAllData();
    } catch (err: any) {
      setError(err.message || 'Permission o\'chirishda xatolik');
    } finally {
      setActionLoading(false);
    }
  };

  const handleCreateTeam = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!teamName.trim()) return;

    setActionLoading(true);
    setError(null);
    try {
      await axiosClient.post('/teams', {
        name: teamName,
        department_id: teamDeptId,
      });

      setTeamName('');
      setTeamCode('');
      setTeamDeptId(null);
      showNotification('Yangi Xizmat Guruhi (Team) muvaffaqiyatli yaratildi!');
      fetchAllData();
    } catch (err: any) {
      setError(err.message || 'Guruh yaratishda xatolik yuz berdi');
    } finally {
      setActionLoading(false);
    }
  };

  const handleDeleteTeam = async (id: number) => {
    if (!window.confirm('Haqiqatdan ham ushbu guruhni o\'chirmoqchimisiz?')) return;
    setActionLoading(true);
    try {
      await axiosClient.delete(`/teams/${id}`);
      showNotification('Guruh o\'chirildi');
      fetchAllData();
    } catch (err: any) {
      setError(err.message || 'Guruhni o\'chirishda xatolik');
    } finally {
      setActionLoading(false);
    }
  };

  const handleAddTeamMember = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTeamId || !addTeamMemberUserId) return;

    setActionLoading(true);
    try {
      await axiosClient.post(`/teams/${selectedTeamId}/members/${addTeamMemberUserId}`);
      showNotification('Xodim guruhga muvaffaqiyatli qo\'shildi!');
      setAddTeamMemberUserId(null);
      fetchAllData();
    } catch (err: any) {
      setError(err.message || 'Xodimni guruhga biriktirishda xatolik');
    } finally {
      setActionLoading(false);
    }
  };

  const handleAssignSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedUserId) return;

    setActionLoading(true);
    setError(null);
    try {
      await axiosClient.post(`/users/${selectedUserId}/assign-role`, {
        role_id: selectedRoleId ?? 0,
        permissions: selectedPermIds,
        department_id: selectedDeptId,
        branch_id: selectedBranchId,
        position_id: selectedPosId,
        team_ids: selectedTeamIds,
      });

      showNotification('Xodimgaga rol, bo\'lim, guruhlar va huquqlar biriktirildi!');
      axiosClient.get('/auth/me').catch(() => axiosClient.get('/me')).then((res) => {
        const u = res?.data?.user?.data || res?.data?.user;
        if (u) {
          useAuthStore.getState().setUser(u);
        }
      }).catch(() => {});
      fetchAllData();
    } catch (err: any) {
      setError(err.message || 'Biriktirishda xatolik yuz berdi');
    } finally {
      setActionLoading(false);
    }
  };

  const togglePermId = (id: number) => {
    setSelectedPermIds((prev) =>
      prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id]
    );
  };

  const toggleRolePermId = (id: number) => {
    setRolePermIds((prev) =>
      prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id]
    );
  };

  const toggleEditRolePermId = (id: number) => {
    setEditRolePermIds((prev) =>
      prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id]
    );
  };

  const toggleTeamId = (id: number) => {
    setSelectedTeamIds((prev) =>
      prev.includes(id) ? prev.filter((t) => t !== id) : [...prev, id]
    );
  };

  // Edit Handlers
  const openEditRole = (role: Role) => {
    setEditingRole(role);
    setEditRoleName(role.name);
    setEditRoleGuard(role.guard_name);
    setEditRoleDesc(role.description || '');
    const pIds = role.permission_ids || role.permissions?.map((p: any) => p.id) || [];
    setEditRolePermIds(pIds);
  };

  const handleEditRoleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingRole || !editRoleName.trim()) return;

    setActionLoading(true);
    setError(null);
    try {
      await axiosClient.put(`/roles/${editingRole.id}`, {
        name: editRoleName,
        guard_name: editRoleGuard,
        description: editRoleDesc || null,
        permissions: editRolePermIds,
      });
      showNotification('Rol va uning huquqlari muvaffaqiyatli tahrirlandi!');
      setEditingRole(null);
      axiosClient.get('/auth/me').catch(() => axiosClient.get('/me')).then((res) => {
        const u = res?.data?.user?.data || res?.data?.user;
        if (u) {
          useAuthStore.getState().setUser(u);
        }
      }).catch(() => {});
      fetchAllData();
    } catch (err: any) {
      setError(err.message || 'Rolni tahrirlashda xatolik');
    } finally {
      setActionLoading(false);
    }
  };

  const openEditPermission = (perm: Permission) => {
    setEditingPermission(perm);
    setEditPermName(perm.name);
    setEditPermGuard(perm.guard_name);
    setEditPermModule(perm.module);
    setEditPermDesc(perm.description || '');
  };

  const handleEditPermissionSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingPermission || !editPermName.trim()) return;

    setActionLoading(true);
    setError(null);
    try {
      await axiosClient.put(`/permissions/${editingPermission.id}`, {
        name: editPermName,
        guard_name: editPermGuard,
        module: editPermModule,
        description: editPermDesc || null,
      });
      showNotification('Permission muvaffaqiyatli tahrirlandi!');
      setEditingPermission(null);
      fetchAllData();
    } catch (err: any) {
      setError(err.message || 'Permissionni tahrirlashda xatolik');
    } finally {
      setActionLoading(false);
    }
  };

  const openEditDepartment = (dept: Department) => {
    setEditingDepartment(dept);
    setEditDeptName(dept.name);
    setEditDeptBranchId(dept.branch_id ?? null);
  };

  const handleEditDepartmentSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingDepartment || !editDeptName.trim()) return;

    setActionLoading(true);
    setError(null);
    try {
      await axiosClient.put(`/departments/${editingDepartment.id}`, {
        name: editDeptName,
        branch_id: editDeptBranchId,
      });
      showNotification('Bo\'lim muvaffaqiyatli tahrirlandi!');
      setEditingDepartment(null);
      fetchAllData();
    } catch (err: any) {
      setError(err.message || 'Bo\'limni tahrirlashda xatolik');
    } finally {
      setActionLoading(false);
    }
  };

  const openEditTeam = (team: Team) => {
    setEditingTeam(team);
    setEditTeamName(team.name);
    setEditTeamDeptId(team.department_id ?? null);
  };

  const handleEditTeamSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingTeam || !editTeamName.trim()) return;

    setActionLoading(true);
    setError(null);
    try {
      await axiosClient.put(`/teams/${editingTeam.id}`, {
        name: editTeamName,
        department_id: editTeamDeptId,
      });
      showNotification('Guruh muvaffaqiyatli tahrirlandi!');
      setEditingTeam(null);
      fetchAllData();
    } catch (err: any) {
      setError(err.message || 'Guruhni tahrirlashda xatolik');
    } finally {
      setActionLoading(false);
    }
  };

  // Show members ("X kishi") for a department (derived from loaded users).
  const openDepartmentMembers = (dept: Department) => {
    const members = users
      .filter((u) => u.departmentId === dept.id)
      .map((u) => ({ id: u.id, name: u.name, username: u.username }));
    setMembersModal({ title: `${dept.name} — xodimlar`, members });
  };

  // Show members ("X kishi") for a role (derived from loaded users).
  const openRoleMembers = (role: Role) => {
    const members = users
      .filter((u) => u.roleId === role.id)
      .map((u) => ({ id: u.id, name: u.name, username: u.username }));
    setMembersModal({ title: `${role.name} — biriktirilgan xodimlar`, members });
  };

  // Show members for a team/group — fetched from the backend.
  const openTeamMembers = async (team: Team) => {
    setMembersModal({ title: `${team.name} — guruh a'zolari`, members: [] });
    setMembersLoading(true);
    try {
      const res = await axiosClient.get<{ data: any[] }>(`/teams/${team.id}/members`);
      const raw = res.data.data || [];
      const members = raw.map((m: any) => ({
        id: m.id ?? m.user_id,
        name: m.name || m.username || `#${m.id ?? m.user_id}`,
        username: m.username,
      }));
      setMembersModal({ title: `${team.name} — guruh a'zolari`, members });
    } catch (err: any) {
      setError(err.message || 'Guruh a\'zolarini yuklashda xatolik');
      setMembersModal(null);
    } finally {
      setMembersLoading(false);
    }
  };

  const modulesList = Array.from(new Set(permissions.map((p) => p.module || 'CORE')));

  const filteredPermissions = permissions.filter((p) => {
    const matchesSearch = p.name.toLowerCase().includes(permSearch.toLowerCase()) || (p.description && p.description.toLowerCase().includes(permSearch.toLowerCase()));
    const matchesModule = permModuleFilter === 'ALL' || p.module === permModuleFilter;
    return matchesSearch && matchesModule;
  });

  const filteredUsers = users.filter((u) =>
    u.name.toLowerCase().includes(userSearch.toLowerCase()) ||
    u.username.toLowerCase().includes(userSearch.toLowerCase()) ||
    (u.departmentName && u.departmentName.toLowerCase().includes(userSearch.toLowerCase()))
  );

  // Defaultda faqat 5 ta xodim ko'rsatiladi — qolganlari qidiruv orqali chiqadi
  const isUserSearchActive = userSearch.trim().length > 0;
  const visibleUsers = isUserSearchActive ? filteredUsers : filteredUsers.slice(0, 5);

  return (
    <div className="w-full px-4 sm:px-6 lg:px-8 py-6 space-y-6 animate-fadeIn">
      {/* Top Header Row with Refresh */}
      <div className="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
          <h1 className="text-2xl font-black text-slate-900 dark:text-slate-100 flex items-center space-x-2">
            <ShieldCheck className="w-6 h-6 text-purple-600" />
            <span>Rollar, Bo'limlar, Guruhlar va Huquqlar Boshqaruvi</span>
          </h1>
        </div>

        <button
          onClick={fetchAllData}
          disabled={loading}
          className="inline-flex items-center space-x-2 px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold text-xs transition-all"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          <span>Yangilash</span>
        </button>
      </div>

      {/* Status Alerts */}
      {message && (
        <div className="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-extrabold flex items-center space-x-2 animate-fadeIn">
          <Check className="w-5 h-5 flex-shrink-0" />
          <span>{message}</span>
        </div>
      )}

      {error && (
        <div className="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs font-extrabold flex items-center space-x-2 animate-fadeIn">
          <AlertCircle className="w-5 h-5 flex-shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {/* Main Tab Navigation */}
      <div className="flex items-center space-x-2 p-1 rounded-2xl bg-slate-200/80 dark:bg-slate-800/90 w-full overflow-x-auto">
        <button
          onClick={() => setActiveTab('departments')}
          className={`flex-1 py-2.5 px-4 rounded-xl text-xs font-extrabold transition-all flex items-center justify-center space-x-2 min-w-[130px] ${
            activeTab === 'departments'
              ? 'bg-white dark:bg-slate-900 text-brand-500 shadow-sm'
              : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
          }`}
        >
          <Building className="w-4 h-4" />
          <span>Bo'limlar ({departments.length})</span>
        </button>

        <button
          onClick={() => setActiveTab('roles')}
          className={`flex-1 py-2.5 px-4 rounded-xl text-xs font-extrabold transition-all flex items-center justify-center space-x-2 min-w-[130px] ${
            activeTab === 'roles'
              ? 'bg-white dark:bg-slate-900 text-purple-600 shadow-sm'
              : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
          }`}
        >
          <ShieldCheck className="w-4 h-4" />
          <span>Rollar ({roles.length})</span>
        </button>

        <button
          onClick={() => setActiveTab('permissions')}
          className={`flex-1 py-2.5 px-4 rounded-xl text-xs font-extrabold transition-all flex items-center justify-center space-x-2 min-w-[130px] ${
            activeTab === 'permissions'
              ? 'bg-white dark:bg-slate-900 text-amber-500 shadow-sm'
              : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
          }`}
        >
          <Key className="w-4 h-4" />
          <span>Permission'lar ({permissions.length})</span>
        </button>

        <button
          onClick={() => setActiveTab('teams')}
          className={`flex-1 py-2.5 px-4 rounded-xl text-xs font-extrabold transition-all flex items-center justify-center space-x-2 min-w-[150px] ${
            activeTab === 'teams'
              ? 'bg-white dark:bg-slate-900 text-emerald-500 shadow-sm'
              : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
          }`}
        >
          <UsersRound className="w-4 h-4" />
          <span>Guruhlar / Teams ({teams.length})</span>
        </button>

        <button
          onClick={() => setActiveTab('assignments')}
          className={`flex-1 py-2.5 px-4 rounded-xl text-xs font-extrabold transition-all flex items-center justify-center space-x-2 min-w-[160px] ${
            activeTab === 'assignments'
              ? 'bg-white dark:bg-slate-900 text-brand-500 shadow-sm'
              : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
          }`}
        >
          <UserCheck className="w-4 h-4" />
          <span>Xodimlarni Biriktirish</span>
        </button>
      </div>

      {/* Full-width Tab Content */}
      <div className="bg-white dark:bg-slate-800 rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-slate-700 shadow-xl w-full">
        {/* ==================== TAB 1: DEPARTMENTS ==================== */}
        {activeTab === 'departments' && (
          <div className="space-y-6">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              {/* Department Create Form */}
              <div className="bg-slate-50 dark:bg-slate-900/50 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 space-y-4">
                <div className="flex items-center space-x-2">
                  <div className="p-2 rounded-xl bg-brand-500/10 text-brand-500">
                    <Plus className="w-5 h-5" />
                  </div>
                  <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100">
                    Yangi Bo'lim Yaratish
                  </h3>
                </div>

                <form onSubmit={handleCreateDepartment} className="space-y-4">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                      Bo'lim Nomi *
                    </label>
                    <input
                      type="text"
                      value={deptName}
                      onChange={(e) => setDeptName(e.target.value)}
                      placeholder="Masalan: Hardware bo'limi, Software bo'limi"
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                      required
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                      Biriktirilgan Filial
                    </label>
                    <select
                      value={deptBranchId || ''}
                      onChange={(e) => setDeptBranchId(e.target.value ? Number(e.target.value) : null)}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                    >
                      <option value="">-- Bosh Ofis / Markaziy --</option>
                      {branches.map((b) => (
                        <option key={b.id} value={b.id}>
                          {b.name} ({b.code})
                        </option>
                      ))}
                    </select>
                  </div>

                  <button
                    type="submit"
                    disabled={actionLoading}
                    className="w-full py-3 px-4 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs shadow-md transition-all disabled:opacity-50 cursor-pointer flex items-center justify-center space-x-2"
                  >
                    <Plus className="w-4 h-4" />
                    <span>Bo'limni Saqlash</span>
                  </button>
                </form>
              </div>

              {/* Department List */}
              <div className="lg:col-span-2 space-y-4">
                <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100 flex items-center space-x-2">
                  <FolderTree className="w-5 h-5 text-brand-500" />
                  <span>Mavjud Bo'limlar Ro'yxati</span>
                </h3>

                <div className="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-700">
                  <table className="w-full text-left border-collapse">
                    <thead>
                      <tr className="bg-slate-100 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 text-[11px] font-extrabold uppercase">
                        <th className="py-3 px-4">ID</th>
                        <th className="py-3 px-4">Bo'lim Nomi</th>
                        <th className="py-3 px-4">Kodi</th>
                        <th className="py-3 px-4">Xodimlar</th>
                        <th className="py-3 px-4 text-right">Amallar</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-700 text-xs font-bold text-slate-800 dark:text-slate-200">
                      {departments.map((d) => {
                        const empCount = users.filter((u) => u.departmentId === d.id).length;
                        return (
                          <tr key={d.id} className="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors">
                            <td className="py-3 px-4 text-slate-400">#{d.id}</td>
                            <td className="py-3 px-4 font-extrabold text-slate-900 dark:text-slate-100">{d.name}</td>
                            <td className="py-3 px-4">
                              <span className="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-mono text-[10px]">
                                {d.code}
                              </span>
                            </td>
                            <td className="py-3 px-4">
                              <button
                                type="button"
                                onClick={() => openDepartmentMembers(d)}
                                className="inline-flex items-center space-x-1 px-2.5 py-1 rounded-full bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300 text-[11px] hover:bg-blue-100 dark:hover:bg-blue-900/60 transition-colors cursor-pointer"
                                title="Xodimlarni ko'rish"
                              >
                                <Users className="w-3 h-3" />
                                <span>{empCount} kishi</span>
                              </button>
                            </td>
                            <td className="py-3 px-4 text-right space-x-1">
                              <button
                                onClick={() => openEditDepartment(d)}
                                className="p-1.5 rounded-lg text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-950/40 transition-colors"
                                title="Tahrirlash"
                              >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                              </button>
                              <button
                                onClick={() => handleDeleteDepartment(d.id)}
                                className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
                                title="O'chirish"
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* ==================== TAB 2: ROLES ==================== */}
        {activeTab === 'roles' && (
          <div className="space-y-6">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              {/* Role Form */}
              <div className="bg-slate-50 dark:bg-slate-900/50 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 space-y-4">
                <div className="flex items-center space-x-2">
                  <div className="p-2 rounded-xl bg-purple-500/10 text-purple-500">
                    <Plus className="w-5 h-5" />
                  </div>
                  <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100">
                    Yangi Rol Yaratish
                  </h3>
                </div>

                <form onSubmit={handleCreateRole} className="space-y-4">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                      Rol Nomi *
                    </label>
                    <input
                      type="text"
                      value={roleName}
                      onChange={(e) => setRoleName(e.target.value)}
                      placeholder="Masalan: Senior Support Manager"
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-purple-500"
                      required
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                      Guard Name
                    </label>
                    <input
                      type="text"
                      value={roleGuard}
                      onChange={(e) => setRoleGuard(e.target.value)}
                      placeholder="web / api"
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-purple-500"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                      Tavsif (Description)
                    </label>
                    <textarea
                      rows={2}
                      value={roleDesc}
                      onChange={(e) => setRoleDesc(e.target.value)}
                      placeholder="Rol majburiyatlari haqida izoh..."
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-purple-500"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">
                      Rolga Biriktiriladigan Permission'lar (Huquqlar):
                    </label>
                    <GroupedPermissionSelector
                      permissions={permissions}
                      selectedIds={rolePermIds}
                      onToggle={toggleRolePermId}
                      onSelectGroup={(ids, select) => {
                        setRolePermIds((prev) => select ? Array.from(new Set([...prev, ...ids])) : prev.filter((id) => !ids.includes(id)));
                      }}
                    />
                  </div>

                  <button
                    type="submit"
                    disabled={actionLoading}
                    className="w-full py-3 px-4 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-md transition-all disabled:opacity-50 cursor-pointer flex items-center justify-center space-x-2"
                  >
                    <Plus className="w-4 h-4" />
                    <span>Yangi Rol Yaratish</span>
                  </button>
                </form>
              </div>

              {/* Roles Cards */}
              <div className="lg:col-span-2 space-y-4">
                <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100 flex items-center space-x-2">
                  <ShieldCheck className="w-5 h-5 text-purple-500" />
                  <span>Tizimdagi Rollar Ro'yxati</span>
                </h3>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {roles.map((r) => {
                    const assignedCount = users.filter((u) => u.roleId === r.id).length;
                    const rolePerms = r.permissions || [];
                    return (
                      <div
                        key={r.id}
                        className="p-5 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 space-y-3 relative group"
                      >
                        <div className="flex items-start justify-between">
                          <div>
                            <span className="px-2.5 py-0.5 rounded-full bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-300 text-[10px] font-extrabold">
                              #{r.id} Guard: {r.guard_name || 'web'}
                            </span>
                            <h4 className="text-base font-extrabold text-slate-900 dark:text-slate-100 mt-1">
                              {r.name}
                            </h4>
                          </div>

                          <div className="flex items-center space-x-1 transition-opacity">
                            <button
                              onClick={() => openEditRole(r)}
                              className="p-1.5 rounded-lg text-blue-500 hover:bg-blue-100 dark:hover:bg-blue-950/60 transition-colors"
                              title="Rolni tahrirlash"
                            >
                              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </button>
                            <button
                              onClick={() => handleDeleteRole(r.id)}
                              className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-100 dark:hover:bg-rose-950/60 transition-colors"
                              title="Rolni o'chirish"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </div>
                        </div>

                        {r.description && (
                          <p className="text-xs text-slate-500 dark:text-slate-400 font-medium line-clamp-2">
                            {r.description}
                          </p>
                        )}

                        <div className="pt-2 border-t border-slate-200/60 dark:border-slate-800 space-y-2 text-xs font-bold text-slate-600 dark:text-slate-300">
                          <div>
                            <span className="text-xs text-slate-500 font-extrabold block mb-1.5">Rol Permission'lari (Biriktirilgan Huquqlar):</span>
                            {rolePerms.length === 0 ? (
                              <span className="text-xs text-slate-400 italic">Huquqlar biriktirilmagan</span>
                            ) : (
                              <div className="flex flex-wrap gap-1.5 max-h-36 overflow-y-auto p-1">
                                {rolePerms.map((p: any) => (
                                  <span key={p.id || p.name} className="px-3 py-1.5 rounded-xl bg-purple-100 dark:bg-purple-950/80 text-purple-800 dark:text-purple-200 text-xs font-extrabold shadow-sm border border-purple-200 dark:border-purple-800 font-mono">
                                    {p.name}
                                  </span>
                                ))}
                              </div>
                            )}
                          </div>

                          <button
                            type="button"
                            onClick={() => openRoleMembers(r)}
                            className="flex items-center space-x-1 text-slate-500 hover:text-purple-600 dark:hover:text-purple-300 transition-colors cursor-pointer pt-1"
                            title="Xodimlarni ko'rish"
                          >
                            <Users className="w-3.5 h-3.5" />
                            <span>{assignedCount} biriktirilgan xodim(lar)</span>
                          </button>
                          {(r as any).users && (r as any).users.length > 0 && (
                            <div className="flex flex-wrap gap-1">
                              {(r as any).users.map((u: any) => (
                                <span
                                  key={u.id}
                                  className="inline-flex items-center px-2 py-0.5 rounded-full bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-300 text-[10px] font-medium border border-purple-200 dark:border-purple-800"
                                >
                                  {u.name}
                                </span>
                              ))}
                            </div>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* ==================== TAB 3: PERMISSIONS ==================== */}
        {activeTab === 'permissions' && (
          <div className="space-y-6">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              {/* Permission Create Form */}
              <div className="bg-slate-50 dark:bg-slate-900/50 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 space-y-4">
                <div className="flex items-center space-x-2">
                  <div className="p-2 rounded-xl bg-amber-500/10 text-amber-500">
                    <Key className="w-5 h-5" />
                  </div>
                  <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100">
                    Yangi Permission Yaratish
                  </h3>
                </div>

                <form onSubmit={handleCreatePermission} className="space-y-4">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                      Permission Nomi (Key) *
                    </label>
                    <input
                      type="text"
                      value={permName}
                      onChange={(e) => setPermName(e.target.value)}
                      placeholder="Masalan: tickets.close"
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                      required
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                      Tavsif (Description)
                    </label>
                    <textarea
                      rows={2}
                      value={permDesc}
                      onChange={(e) => setPermDesc(e.target.value)}
                      placeholder="Huquq vazifasi va ruxsatlar chegarasi..."
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500"
                    />
                  </div>

                  <button
                    type="submit"
                    disabled={actionLoading}
                    className="w-full py-3 px-4 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs shadow-md transition-all disabled:opacity-50 cursor-pointer flex items-center justify-center space-x-2"
                  >
                    <Plus className="w-4 h-4" />
                    <span>Permission Yaratish</span>
                  </button>
                </form>
              </div>

              {/* Permission List & Filtering */}
              <div className="lg:col-span-2 space-y-4">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                  <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100 flex items-center space-x-2">
                    <Key className="w-5 h-5 text-amber-500" />
                    <span>Permission'lar Ro'yxati</span>
                  </h3>

                  {/* Search Filter */}
                  <div className="relative">
                    <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                    <input
                      type="text"
                      value={permSearch}
                      onChange={(e) => setPermSearch(e.target.value)}
                      placeholder="Qidirish..."
                      className="pl-9 pr-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-[500px] overflow-y-auto pr-1">
                  {filteredPermissions.map((p) => (
                    <div
                      key={p.id}
                      className="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 space-y-2 flex items-start justify-between group hover:border-amber-500/50 transition-colors"
                    >
                      <div className="space-y-1">
                        <div className="flex items-center space-x-2">
                          <span className="font-mono font-bold text-xs text-slate-900 dark:text-slate-100">
                            {p.name}
                          </span>
                        </div>

                        {p.description && (
                          <p className="text-[11px] text-slate-500 dark:text-slate-400 font-normal">
                            {p.description}
                          </p>
                        )}
                      </div>

                      <div className="flex items-center space-x-1 transition-opacity">
                        <button
                          onClick={() => openEditPermission(p)}
                          className="p-1.5 rounded-lg text-blue-500 hover:bg-blue-100 dark:hover:bg-blue-950/60 transition-colors"
                          title="Huquqni tahrirlash"
                        >
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </button>
                        <button
                          onClick={() => handleDeletePermission(p.id)}
                          className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-100 dark:hover:bg-rose-950/60 transition-colors"
                          title="Huquqni o'chirish"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* ==================== TAB 4: TEAMS / GURUHLAR ==================== */}
        {activeTab === 'teams' && (
          <div className="space-y-6">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              {/* Team Create Form */}
              <div className="bg-slate-50 dark:bg-slate-900/50 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 space-y-4">
                <div className="flex items-center space-x-2">
                  <div className="p-2 rounded-xl bg-emerald-500/10 text-emerald-500">
                    <UsersRound className="w-5 h-5" />
                  </div>
                  <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100">
                    Yangi Xizmat Guruhi Yaratish
                  </h3>
                </div>

                <form onSubmit={handleCreateTeam} className="space-y-4">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                      Guruh Nomi (Masalan: Texnik xizmat, NOC, Pochta) *
                    </label>
                    <input
                      type="text"
                      value={teamName}
                      onChange={(e) => setTeamName(e.target.value)}
                      placeholder="Texnik xizmat"
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                      required
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                      Tegishli Bo'lim *
                    </label>
                    <select
                      value={teamDeptId || ''}
                      onChange={(e) => setTeamDeptId(e.target.value ? Number(e.target.value) : null)}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                      required
                    >
                      <option value="">-- Tegishli Bo'limni tanlang --</option>
                      {departments.map((d) => (
                        <option key={d.id} value={d.id}>
                          {d.name}
                        </option>
                      ))}
                    </select>
                  </div>

                  <button
                    type="submit"
                    disabled={actionLoading}
                    className="w-full py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md transition-all disabled:opacity-50 cursor-pointer flex items-center justify-center space-x-2"
                  >
                    <Plus className="w-4 h-4" />
                    <span>Guruhni Saqlash</span>
                  </button>
                </form>

                {/* Add Member to Selected Team */}
                {teams.length > 0 && (
                  <div className="pt-4 border-t border-slate-200 dark:border-slate-700 space-y-3">
                    <h4 className="text-xs font-extrabold text-slate-900 dark:text-slate-100 flex items-center space-x-1.5">
                      <UserPlus className="w-4 h-4 text-emerald-500" />
                      <span>Guruhga Xodim Biriktirish</span>
                    </h4>

                    <form onSubmit={handleAddTeamMember} className="space-y-2">
                      <select
                        value={selectedTeamId || ''}
                        onChange={(e) => setSelectedTeamId(e.target.value ? Number(e.target.value) : null)}
                        className="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                        required
                      >
                        <option value="">-- Guruhni tanlang --</option>
                        {teams.map((t) => (
                          <option key={t.id} value={t.id}>
                            {t.name} ({t.code})
                          </option>
                        ))}
                      </select>

                      <select
                        value={addTeamMemberUserId || ''}
                        onChange={(e) => setAddTeamMemberUserId(e.target.value ? Number(e.target.value) : null)}
                        className="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                        required
                      >
                        <option value="">-- Xodimni tanlang --</option>
                        {users.map((u) => (
                          <option key={u.id} value={u.id}>
                            {u.name} (@{u.username})
                          </option>
                        ))}
                      </select>

                      <button
                        type="submit"
                        disabled={actionLoading}
                        className="w-full py-2 px-3 rounded-xl bg-slate-900 dark:bg-slate-700 text-white font-bold text-xs hover:bg-slate-800 transition-colors"
                      >
                        Guruhga Qo'shish
                      </button>
                    </form>
                  </div>
                )}
              </div>

              {/* Teams List */}
              <div className="lg:col-span-2 space-y-4">
                <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100 flex items-center space-x-2">
                  <UsersRound className="w-5 h-5 text-emerald-500" />
                  <span>Xizmat Guruhlari (Teams) Ro'yxati</span>
                </h3>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {teams.map((t) => {
                    const dept = departments.find((d) => d.id === t.department_id);
                    return (
                      <div
                        key={t.id}
                        className="p-5 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 space-y-3 relative group"
                      >
                        <div className="flex items-start justify-between">
                          <div>
                            <span className="px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 text-[10px] font-extrabold">
                              Bo'lim: {dept?.name || 'Markaziy'}
                            </span>
                            <h4 className="text-base font-extrabold text-slate-900 dark:text-slate-100 mt-1">
                              {t.name}
                            </h4>
                          </div>

                        <div className="flex items-center space-x-1 transition-opacity">
                          <button
                            onClick={() => openEditTeam(t)}
                            className="p-1.5 rounded-lg text-blue-500 hover:bg-blue-100 dark:hover:bg-blue-950/60 transition-colors"
                            title="Guruhni tahrirlash"
                          >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                          </button>
                          <button
                            onClick={() => handleDeleteTeam(t.id)}
                            className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-100 dark:hover:bg-rose-950/60 transition-colors"
                            title="Guruhni o'chirish"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </div>

                      <div className="pt-2 border-t border-slate-200/60 dark:border-slate-800 flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-300">
                        <button
                          type="button"
                          onClick={() => openTeamMembers(t)}
                          className="inline-flex items-center space-x-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300 text-[11px] hover:bg-emerald-100 dark:hover:bg-emerald-900/60 transition-colors cursor-pointer"
                          title="Guruh a'zolarini ko'rish"
                        >
                          <Users className="w-3.5 h-3.5" />
                          <span>{t.members_count ?? 0} kishi</span>
                        </button>
                        <span className="text-[10px] text-slate-400 font-medium">Zayavkalar shu guruhga boradi</span>
                      </div>
                    </div>
                    );
                  })}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* ==================== TAB 5: ASSIGNMENTS ==================== */}
        {activeTab === 'assignments' && (
          <div className="space-y-8" id="assignment-form-section">
            <form onSubmit={handleAssignSubmit} className="space-y-6">
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Left Side: Select User & Role */}
                <div className="space-y-4">
                  <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100 flex items-center space-x-2">
                    <UserCheck className="w-5 h-5 text-brand-500" />
                    <span>1. Xodimlarga Rol va Tashkilot Biriktirish</span>
                  </h3>

                  {/* Searchable Combobox for 200+ Employees */}
                  <div className="relative">
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                      Xodim / Foydalanuvchi Izlash va Tanlash * <span className="text-[10px] text-slate-400 font-normal">(200+ xodim ichidan ismi, username yoki bo'limi bo'yicha izlang)</span>
                    </label>
                    <div className="relative">
                      <Search className="w-4 h-4 absolute left-3.5 top-3.5 text-slate-400" />
                      <input
                        type="text"
                        value={empSearchQuery}
                        onChange={(e) => {
                          setEmpSearchQuery(e.target.value);
                          setEmpComboboxOpen(true);
                        }}
                        onFocus={() => setEmpComboboxOpen(true)}
                        placeholder="Xodim ismini yozing... (Masalan: Sardor, @admin, IT)"
                        className="w-full pl-10 pr-10 py-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-xs font-extrabold focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm"
                      />
                      {empSearchQuery && (
                        <button
                          type="button"
                          onClick={() => {
                            setEmpSearchQuery('');
                            setEmpComboboxOpen(false);
                          }}
                          className="absolute right-3 top-3 text-xs text-slate-400 hover:text-slate-600 font-bold"
                        >
                          ✕
                        </button>
                      )}
                    </div>

                    {/* Combobox Dropdown List */}
                    {empComboboxOpen && (
                      <div className="absolute z-30 mt-1 w-full max-h-60 overflow-y-auto rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl divide-y divide-slate-100 dark:divide-slate-700">
                        {(() => {
                          const q = empSearchQuery.toLowerCase().trim();
                          const allMatches = users.filter(
                            (u) =>
                              !q ||
                              (u.name && u.name.toLowerCase().includes(q)) ||
                              (u.username && u.username.toLowerCase().includes(q)) ||
                              (u.email && u.email.toLowerCase().includes(q)) ||
                              (u.departmentName && u.departmentName.toLowerCase().includes(q)) ||
                              (u.roleName && u.roleName.toLowerCase().includes(q))
                          );
                          const matches = q ? allMatches : allMatches.slice(0, 5);

                          if (matches.length === 0) {
                            return (
                              <div className="p-4 text-center text-xs text-slate-400 italic">
                                Claviatura orqali qidirilgan xodim topilmadi
                              </div>
                            );
                          }

                          return (
                            <>
                              {matches.map((u) => (
                                <div
                                  key={u.id}
                                  onClick={() => {
                                    setSelectedUserId(u.id);
                                    setEmpSearchQuery(u.name);
                                    setEmpComboboxOpen(false);
                                  }}
                                  className={`p-3 flex items-center space-x-3 cursor-pointer transition-colors hover:bg-brand-50 dark:hover:bg-brand-950/40 ${
                                    selectedUserId === u.id ? 'bg-brand-50/80 dark:bg-brand-950/60 font-bold' : ''
                                  }`}
                                >
                                  <div className="w-8 h-8 rounded-xl bg-brand-500 text-white font-bold text-xs flex items-center justify-center flex-shrink-0">
                                    {u.name.charAt(0).toUpperCase()}
                                  </div>
                                  <div className="flex-1 min-w-0">
                                    <div className="text-xs font-bold text-slate-900 dark:text-slate-100 truncate">
                                      {u.name} <span className="text-[10px] text-slate-400 font-normal">(@{u.username})</span>
                                    </div>
                                    <div className="text-[10px] text-slate-500 font-medium truncate">
                                      {u.departmentName} — <span className="text-purple-600 dark:text-purple-400">{u.roleName}</span>
                                    </div>
                                  </div>
                                  {selectedUserId === u.id && (
                                    <Check className="w-4 h-4 text-brand-500 flex-shrink-0" />
                                  )}
                                </div>
                              ))}
                              {!q && allMatches.length > 5 && (
                                <div className="p-2.5 text-center text-[10px] font-bold text-slate-400 italic">
                                  Yana {allMatches.length - 5} ta xodim bor — qidiruvga ism yozing
                                </div>
                              )}
                            </>
                          );
                        })()}
                      </div>
                    )}
                  </div>

                  {/* Selected User Visual Profile Card */}
                  {(() => {
                    const selUser = users.find((u) => u.id === selectedUserId);
                    if (!selUser) return null;
                    return (
                      <div className="p-4 rounded-2xl bg-gradient-to-r from-brand-500/10 via-purple-500/10 to-blue-500/10 border border-brand-500/30 flex items-center space-x-4 animate-fadeIn">
                        <div className="w-12 h-12 rounded-2xl bg-brand-500 text-white font-black text-lg flex items-center justify-center shadow-md flex-shrink-0">
                          {selUser.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="flex-1 space-y-1 min-w-0">
                          <div className="flex items-center justify-between">
                            <h4 className="text-xs font-black text-slate-900 dark:text-slate-100 truncate">
                              Tanlangan: <span className="text-brand-500">{selUser.name}</span> <span className="text-[11px] font-normal text-slate-400">(@{selUser.username})</span>
                            </h4>
                            <span className="text-[10px] font-mono text-slate-400">ID: #{selUser.id}</span>
                          </div>
                          <div className="flex flex-wrap gap-1.5 text-[10px] font-bold">
                            <span className="px-2.5 py-0.5 rounded-full bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300">
                              Hozirgi Rol: {selUser.roleName || 'Oddiy foydalanuvchi'}
                            </span>
                            <span className="px-2.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300">
                              Bo'lim: {selUser.departmentName || 'Bo\'limsiz'}
                            </span>
                            {selUser.teams && selUser.teams.map((t: any) => (
                              <span key={t.id} className="px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">
                                Guruh: {t.name}
                              </span>
                            ))}
                          </div>
                        </div>
                      </div>
                    );
                  })()}

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Rol (Role)
                      </label>
                      <select
                        value={selectedRoleId ?? ''}
                        onChange={(e) => {
                          const v = Number(e.target.value);
                          setSelectedRoleId(v);
                          if (v === 0) setSelectedPermIds([]);
                        }}
                        className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                      >
                        <option value="0">Oddiy foydalanuvchi (rolsiz)</option>
                        {roles.map((r) => (
                          <option key={r.id} value={r.id}>
                            {r.name}
                          </option>
                        ))}
                      </select>
                      <span className="text-[10px] text-slate-400 block mt-1">
                        "Oddiy foydalanuvchi" tanlansa, xodimning roli va to'g'ridan-to'g'ri huquqlari avtomatik olib tashlanadi.
                      </span>
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Bo'lim (Department)
                      </label>
                      <select
                        value={selectedDeptId || ''}
                        onChange={(e) => setSelectedDeptId(e.target.value ? Number(e.target.value) : null)}
                        className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                      >
                        <option value="">-- Bo'limsiz --</option>
                        {departments.map((d) => (
                          <option key={d.id} value={d.id}>
                            {d.name}
                          </option>
                        ))}
                      </select>
                      <span className="text-[10px] text-slate-400 block mt-1">
                        Tashkiliy bo'lim (masalan, IT, Buxgalteriya). Zayavkalar va ruxsatlarni bo'limga ulaydi.
                      </span>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Filial (Branch)
                      </label>
                      <select
                        value={selectedBranchId || ''}
                        onChange={(e) => setSelectedBranchId(e.target.value ? Number(e.target.value) : null)}
                        className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                      >
                        <option value="">-- Filialsiz --</option>
                        {branches.map((b) => (
                          <option key={b.id} value={b.id}>
                            {b.name}
                          </option>
                        ))}
                      </select>
                      <span className="text-[10px] text-slate-400 block mt-1">
                        Hududiy filial yoki Bosh Ofis.
                      </span>
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Lavozim (Position)
                      </label>
                      <select
                        value={selectedPosId || ''}
                        onChange={(e) => setSelectedPosId(e.target.value ? Number(e.target.value) : null)}
                        className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                      >
                        <option value="">-- Lavozimsiz --</option>
                        {positions.map((p) => (
                          <option key={p.id} value={p.id}>
                            {p.name}
                          </option>
                        ))}
                      </select>
                      <span className="text-[10px] text-slate-400 block mt-1">
                        Xodimning rasmiy unvoni/lavozimi.
                      </span>
                    </div>
                  </div>

                  {/* Multi-Team Assignment Section */}
                  <div>
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 flex items-center space-x-1">
                      <UsersRound className="w-4 h-4 text-emerald-500" />
                      <span>Xodim A'zo Bo'lgan Guruhlar (Bir nechta tanlash imkoniyati):</span>
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 max-h-40 overflow-y-auto">
                      {(() => {
                        const deptTeams = teams.filter(
                          (t) => !selectedDeptId || (t as any).department_id === selectedDeptId || (t as any).departmentId === selectedDeptId
                        );

                        if (deptTeams.length === 0) {
                          return (
                            <span className="text-xs text-slate-400 italic col-span-2">
                              {selectedDeptId ? "Ushbu bo'limga tegishli xizmat guruhlari topilmadi" : "Hali guruhlar yaratilmagan"}
                            </span>
                          );
                        }

                        return deptTeams.map((t) => {
                          const isChecked = selectedTeamIds.includes(t.id);
                          return (
                            <label
                              key={t.id}
                              className={`flex items-center space-x-2.5 p-2 rounded-xl border transition-colors cursor-pointer text-xs font-bold ${
                                isChecked
                                  ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-500 text-emerald-700 dark:text-emerald-300'
                                  : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300'
                              }`}
                            >
                              <input
                                type="checkbox"
                                checked={isChecked}
                                onChange={() => toggleTeamId(t.id)}
                                className="rounded text-emerald-600 focus:ring-emerald-500 border-slate-300"
                              />
                              <span>{t.name}</span>
                            </label>
                          );
                        });
                      })()}
                    </div>
                  </div>
                </div>

                {/* Right Side: Collapsible Granular Direct Permissions */}
                <div className="space-y-4">
                  <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100 flex items-center justify-between">
                    <span className="flex items-center space-x-2">
                      <Key className="w-5 h-5 text-amber-500" />
                      <span>2. Qo'shimcha Xususiy Permission'lar</span>
                    </span>
                    <span className="text-[11px] text-slate-400 font-normal">(Roldan tashqari qo'shimcha biriktirish)</span>
                  </h3>

                  <div className="p-4 rounded-2xl bg-amber-500/5 border border-amber-500/20 text-xs text-amber-800 dark:text-amber-300 font-medium">
                    💡 <strong>Eslatma:</strong> Rol tanlanganda roldagi barcha huquqlar xodimga avtomatik o'tadi. Bu yerdan faqat xodimga roldan tashqari alohida qo'shimcha ruxsatlar bermoqchi bo'lsangiz belgilang.
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-[340px] overflow-y-auto p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700">
                    {permissions.map((p) => {
                      const isChecked = selectedPermIds.includes(p.id);
                      return (
                        <label
                          key={p.id}
                          className={`flex items-start space-x-3 p-3 rounded-xl transition-colors cursor-pointer border ${
                            isChecked
                              ? 'bg-white dark:bg-slate-800 border-brand-500 shadow-sm'
                              : 'bg-transparent border-transparent hover:bg-slate-100 dark:hover:bg-slate-800'
                          }`}
                        >
                          <input
                            type="checkbox"
                            checked={isChecked}
                            onChange={() => togglePermId(p.id)}
                            className="mt-0.5 w-4 h-4 rounded text-brand-500 focus:ring-brand-500 border-slate-300"
                          />
                          <div>
                            <span className="font-extrabold text-xs text-slate-900 dark:text-slate-100 block">
                              {p.name}
                            </span>
                            <span className="text-[10px] text-slate-500 font-medium">
                              Modul: {p.module || 'CORE'}
                            </span>
                          </div>
                        </label>
                      );
                    })}
                  </div>
                </div>
              </div>

              <div className="pt-4 border-t border-slate-200 dark:border-slate-700 flex justify-end">
                <button
                  type="submit"
                  disabled={actionLoading}
                  className="px-8 py-3.5 rounded-2xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 text-white font-extrabold text-xs shadow-lg transition-all disabled:opacity-50 cursor-pointer flex items-center space-x-2"
                >
                  <UserCheck className="w-4 h-4" />
                  <span>{actionLoading ? 'Saqlanmoqda...' : 'Biriktirish va Saqlash'}</span>
                </button>
              </div>
            </form>

            {/* Users Overview Table */}
            <div className="pt-8 space-y-4">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100 flex items-center space-x-2">
                  <Users className="w-5 h-5 text-brand-500" />
                  <span>Barcha Xodimlarning Holati va Tahrirlash</span>
                </h3>

                <div className="relative max-w-xs w-full">
                  <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                  <input
                    type="text"
                    value={userSearch}
                    onChange={(e) => setUserSearch(e.target.value)}
                    placeholder="Xodim bo'yicha qidirish..."
                    className="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                  />
                </div>
              </div>

              {!isUserSearchActive && filteredUsers.length > 5 && (
                <p className="text-[11px] text-slate-500 dark:text-slate-400 font-medium flex items-center space-x-1.5">
                  <Info className="w-3.5 h-3.5" />
                  <span>
                    Jami <b>{filteredUsers.length}</b> nafar xodim — dastlabki <b>5 tasi</b> ko'rsatilmoqda. Qolganlarini yuqoridagi qidiruv orqali toping.
                  </span>
                </p>
              )}

              <div className="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-700">
                <table className="w-full text-left border-collapse">
                  <thead>
                    <tr className="bg-slate-100 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 text-[11px] font-extrabold uppercase">
                      <th className="py-3 px-4">Xodim</th>
                      <th className="py-3 px-4">Bo'lim</th>
                      <th className="py-3 px-4">Rol</th>
                      <th className="py-3 px-4">Guruhlar (Teams)</th>
                      <th className="py-3 px-4">Huquqlar (Permissions)</th>
                      <th className="py-3 px-4 text-right">Amallar</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-700 text-xs font-bold text-slate-800 dark:text-slate-200">
                    {visibleUsers.map((u) => (
                      <tr key={u.id} className="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors">
                        <td className="py-3 px-4">
                          <div className="font-extrabold text-slate-900 dark:text-slate-100">{u.name}</div>
                          <div className="text-[10px] text-slate-400">@{u.username}</div>
                        </td>
                        <td className="py-3 px-4">
                          <span className="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300 text-[11px] font-bold">
                            {u.departmentName}
                          </span>
                        </td>
                        <td className="py-3 px-4">
                          <span className="px-2.5 py-1 rounded-lg bg-purple-50 text-purple-600 dark:bg-purple-950/40 dark:text-purple-300 text-[11px] font-bold">
                            {u.roleName}
                          </span>
                        </td>
                        <td className="py-3 px-4">
                          <div className="flex flex-wrap gap-1 max-w-xs">
                            {(!u.teams || u.teams.length === 0) ? (
                              <span className="text-slate-400 font-medium text-[10px] italic">Guruhsiz</span>
                            ) : (
                              u.teams.map((t) => (
                                <span
                                  key={t.id}
                                  className="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 text-[10px] font-bold border border-emerald-200 dark:border-emerald-800"
                                >
                                  {t.name}
                                </span>
                              ))
                            )}
                          </div>
                        </td>
                        <td className="py-3 px-4">
                          <div className="flex flex-wrap gap-1 max-w-md">
                            {!(u.permissions && u.permissions.length > 0) ? (
                              <span className="text-slate-400 font-medium text-[10px]">Huquqlar yo'q</span>
                            ) : (
                              u.permissions.map((perm) => (
                                <span
                                  key={perm}
                                  className="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-[10px] font-mono"
                                >
                                  {perm}
                                </span>
                              ))
                            )}
                          </div>
                        </td>
                        <td className="py-3 px-4 text-right">
                          <button
                            type="button"
                            onClick={() => {
                              setSelectedUserId(u.id);
                              setEmpSearchQuery(u.name);
                              document.getElementById('assignment-form-section')?.scrollIntoView({ behavior: 'smooth' });
                            }}
                            className="px-3 py-1.5 rounded-xl bg-brand-50 dark:bg-brand-950/60 text-brand-600 dark:text-brand-400 hover:bg-brand-100 font-extrabold text-[11px] border border-brand-200 dark:border-brand-800 transition-colors inline-flex items-center space-x-1"
                          >
                            <Pencil className="w-3.5 h-3.5" />
                            <span>Tahrirlash</span>
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* ==================== EDIT MODALS ==================== */}

      {/* Edit Department Modal */}
      {editingDepartment && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" onClick={() => setEditingDepartment(null)}>
          <div className="bg-white dark:bg-slate-800 rounded-3xl p-6 w-full max-w-md border border-slate-200 dark:border-slate-700 shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100 mb-4">Bo'limni Tahrirlash</h3>
            <form onSubmit={handleEditDepartmentSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Bo'lim Nomi *</label>
                <input type="text" value={editDeptName} onChange={(e) => setEditDeptName(e.target.value)} className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500" required />
              </div>
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Filial</label>
                <select value={editDeptBranchId || ''} onChange={(e) => setEditDeptBranchId(e.target.value ? Number(e.target.value) : null)} className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500">
                  <option value="">-- Bosh Ofis / Markaziy --</option>
                  {branches.map((b) => (<option key={b.id} value={b.id}>{b.name} ({b.code})</option>))}
                </select>
              </div>
              <div className="flex justify-end space-x-3 pt-2">
                <button type="button" onClick={() => setEditingDepartment(null)} className="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold">Bekor qilish</button>
                <button type="submit" disabled={actionLoading} className="px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold">Saqlash</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Role Modal */}
      {editingRole && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" onClick={() => setEditingRole(null)}>
          <div className="bg-white dark:bg-slate-800 rounded-3xl p-6 w-full max-w-lg border border-slate-200 dark:border-slate-700 shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100 mb-4">Rolni Tahrirlash</h3>
            <form onSubmit={handleEditRoleSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Rol Nomi *</label>
                <input type="text" value={editRoleName} onChange={(e) => setEditRoleName(e.target.value)} className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-purple-500" required />
              </div>
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Guard Name</label>
                <input type="text" value={editRoleGuard} onChange={(e) => setEditRoleGuard(e.target.value)} className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-purple-500" />
              </div>
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Tavsif</label>
                <textarea rows={2} value={editRoleDesc} onChange={(e) => setEditRoleDesc(e.target.value)} className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-purple-500" />
              </div>
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">Rol Permission'lari (Huquqlar):</label>
                <GroupedPermissionSelector
                  permissions={permissions}
                  selectedIds={editRolePermIds}
                  onToggle={toggleEditRolePermId}
                  onSelectGroup={(ids, select) => {
                    setEditRolePermIds((prev) => select ? Array.from(new Set([...prev, ...ids])) : prev.filter((id) => !ids.includes(id)));
                  }}
                />
              </div>
              <div className="flex justify-end space-x-3 pt-2">
                <button type="button" onClick={() => setEditingRole(null)} className="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold">Bekor qilish</button>
                <button type="submit" disabled={actionLoading} className="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold">Saqlash</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Permission Modal */}
      {editingPermission && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" onClick={() => setEditingPermission(null)}>
          <div className="bg-white dark:bg-slate-800 rounded-3xl p-6 w-full max-w-md border border-slate-200 dark:border-slate-700 shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100 mb-4">Permissionni Tahrirlash</h3>
            <form onSubmit={handleEditPermissionSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Permission Nomi *</label>
                <input type="text" value={editPermName} onChange={(e) => setEditPermName(e.target.value)} className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500" required />
              </div>
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Module</label>
                <input type="text" value={editPermModule} onChange={(e) => setEditPermModule(e.target.value)} className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500" />
              </div>
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Tavsif</label>
                <textarea rows={2} value={editPermDesc} onChange={(e) => setEditPermDesc(e.target.value)} className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500" />
              </div>
              <div className="flex justify-end space-x-3 pt-2">
                <button type="button" onClick={() => setEditingPermission(null)} className="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold">Bekor qilish</button>
                <button type="submit" disabled={actionLoading} className="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold">Saqlash</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Team Modal */}
      {editingTeam && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" onClick={() => setEditingTeam(null)}>
          <div className="bg-white dark:bg-slate-800 rounded-3xl p-6 w-full max-w-md border border-slate-200 dark:border-slate-700 shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100 mb-4">Guruhni Tahrirlash</h3>
            <form onSubmit={handleEditTeamSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Guruh Nomi *</label>
                <input type="text" value={editTeamName} onChange={(e) => setEditTeamName(e.target.value)} className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500" required />
              </div>
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Tegishli Bo'lim</label>
                <select value={editTeamDeptId || ''} onChange={(e) => setEditTeamDeptId(e.target.value ? Number(e.target.value) : null)} className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500">
                  <option value="">-- Bo'limni tanlang --</option>
                  {departments.map((d) => (<option key={d.id} value={d.id}>{d.name}</option>))}
                </select>
              </div>
              <div className="flex justify-end space-x-3 pt-2">
                <button type="button" onClick={() => setEditingTeam(null)} className="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold">Bekor qilish</button>
                <button type="submit" disabled={actionLoading} className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">Saqlash</button>
              </div>
            </form>
          </div>
        </div>
      )}
      {/* Members List Modal ("X kishi" bosilganda) */}
      {membersModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" onClick={() => setMembersModal(null)}>
          <div className="bg-white dark:bg-slate-800 rounded-3xl p-6 w-full max-w-md border border-slate-200 dark:border-slate-700 shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-base font-extrabold text-slate-900 dark:text-slate-100 flex items-center space-x-2">
                <Users className="w-5 h-5 text-brand-500" />
                <span>{membersModal.title}</span>
              </h3>
              <button onClick={() => setMembersModal(null)} className="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <span className="text-lg leading-none">&times;</span>
              </button>
            </div>

            {membersLoading ? (
              <div className="py-8 flex items-center justify-center text-slate-400 text-xs font-bold">
                <RefreshCw className="w-4 h-4 animate-spin mr-2" /> Yuklanmoqda...
              </div>
            ) : membersModal.members.length === 0 ? (
              <div className="py-8 text-center text-slate-400 text-xs font-bold">
                Hozircha xodimlar biriktirilmagan
              </div>
            ) : (
              <div className="space-y-2 max-h-[360px] overflow-y-auto pr-1">
                {membersModal.members.map((m) => (
                  <div key={m.id} className="flex items-center space-x-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700">
                    <div className="w-8 h-8 rounded-full bg-brand-500/10 text-brand-500 flex items-center justify-center font-extrabold text-xs">
                      {m.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                      <div className="text-xs font-extrabold text-slate-900 dark:text-slate-100">{m.name}</div>
                      {m.username && <div className="text-[10px] text-slate-400">@{m.username}</div>}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default RbacManagementPage;
