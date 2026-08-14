import React, { useState, useEffect } from 'react';
import { X, ShieldCheck, UserCheck, Plus, Check, AlertCircle, Building, MapPin, Briefcase } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';

interface Role {
  id: number;
  name: string;
  description?: string;
}

interface Permission {
  id: number;
  name: string;
  description?: string;
}

interface Department {
  id: number;
  name: string;
}

interface Branch {
  id: number;
  name: string;
}

interface Position {
  id: number;
  name: string;
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
  roleId: number | null;
  roleName: string;
  permissions: string[];
}

interface RoleManagementModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess?: () => void;
}

export const RoleManagementModal: React.FC<RoleManagementModalProps> = ({ isOpen, onClose, onSuccess }) => {
  const t = useT();
  const [activeTab, setActiveTab] = useState<'assign' | 'create'>('assign');

  const [roles, setRoles] = useState<Role[]>([]);
  const [permissions, setPermissions] = useState<Permission[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [positions, setPositions] = useState<Position[]>([]);
  const [users, setUsers] = useState<UserWithRole[]>([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Tab 1 state
  const [selectedUserId, setSelectedUserId] = useState<number | null>(null);
  const [selectedRoleId, setSelectedRoleId] = useState<number | null>(null);
  const [selectedDeptId, setSelectedDeptId] = useState<number | null>(null);
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);
  const [selectedPosId, setSelectedPosId] = useState<number | null>(null);
  const [selectedPermIds, setSelectedPermIds] = useState<number[]>([]);

  // Tab 2 state
  const [newRoleName, setNewRoleName] = useState('');
  const [newRoleDesc, setNewRoleDesc] = useState('');

  const fetchData = async () => {
    setLoading(true);
    try {
      const [rolesRes, permsRes, usersRes, deptsRes, branchesRes, positionsRes] = await Promise.all([
        axiosClient.get<{ data: Role[] }>('/roles'),
        axiosClient.get<{ data: Permission[] }>('/permissions'),
        axiosClient.get<{ data: UserWithRole[] }>('/users/roles'),
        axiosClient.get<{ data: Department[] }>('/departments').catch(() => ({ data: { data: [] } })),
        axiosClient.get<{ data: Branch[] }>('/branches').catch(() => ({ data: { data: [] } })),
        axiosClient.get<{ data: Position[] }>('/positions').catch(() => ({ data: { data: [] } })),
      ]);

      setRoles(rolesRes.data.data || []);
      setPermissions(permsRes.data.data || []);
      setDepartments(deptsRes.data.data || []);
      setBranches(branchesRes.data.data || []);
      setPositions(positionsRes.data.data || []);

      const userList = usersRes.data.data || [];
      setUsers(userList);

      if (userList.length > 0 && !selectedUserId) {
        const u = userList[0];
        setSelectedUserId(u.id);
        setSelectedRoleId(u.roleId || (rolesRes.data.data[0]?.id ?? 1));
        setSelectedDeptId(u.departmentId || null);
        setSelectedBranchId(u.branchId || null);
        setSelectedPosId(u.positionId || null);
      }
    } catch (err: any) {
      setError(err.message || t('common.errorGeneric'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (isOpen) {
      fetchData();
    }
  }, [isOpen]);

  useEffect(() => {
    if (selectedUserId) {
      const u = users.find((item) => item.id === selectedUserId);
      if (u) {
        setSelectedRoleId(u.roleId || (roles[0]?.id ?? 1));
        setSelectedDeptId(u.departmentId || null);
        setSelectedBranchId(u.branchId || null);
        setSelectedPosId(u.positionId || null);

        const matchedPermIds = permissions
          .filter((p) => u.permissions.includes(p.name))
          .map((p) => p.id);
        setSelectedPermIds(matchedPermIds);
      }
    }
  }, [selectedUserId]);

  if (!isOpen) return null;

  const togglePerm = (permId: number) => {
    setSelectedPermIds((prev) =>
      prev.includes(permId) ? prev.filter((id) => id !== permId) : [...prev, permId]
    );
  };

  const handleAssignRole = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedUserId || !selectedRoleId) return;

    setSaving(true);
    setMessage(null);
    setError(null);

    try {
      await axiosClient.post(`/users/${selectedUserId}/assign-role`, {
        role_id: selectedRoleId,
        permissions: selectedPermIds,
        department_id: selectedDeptId,
        branch_id: selectedBranchId,
        position_id: selectedPosId,
      });

      setMessage(t('roleModal.assignSuccess'));
      fetchData();
      if (onSuccess) onSuccess();
    } catch (err: any) {
      setError(err.message || t('common.errorGeneric'));
    } finally {
      setSaving(false);
    }
  };

  const handleCreateRole = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newRoleName.trim()) return;

    setSaving(true);
    setMessage(null);
    setError(null);

    try {
      await axiosClient.post('/roles', {
        name: newRoleName,
        guard_name: 'web',
        description: newRoleDesc || undefined,
      });

      setNewRoleName('');
      setNewRoleDesc('');
      setMessage(t('roleModal.roleCreated'));
      fetchData();
      if (onSuccess) onSuccess();
    } catch (err: any) {
      setError(err.message || t('common.errorGeneric'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
      <div className="bg-white dark:bg-slate-800 rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-6 relative overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700">
          <div className="flex items-center space-x-3">
            <div className="p-2.5 rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-950/40">
              <ShieldCheck className="w-6 h-6" />
            </div>
            <div>
              <h2 className="text-xl font-extrabold text-slate-900 dark:text-slate-100">
                {t('roleModal.title')}
              </h2>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {t('roleModal.subtitle')}
              </p>
            </div>
          </div>

          <button
            onClick={onClose}
            className="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Status Alerts */}
        {message && (
          <div className="p-3 rounded-xl bg-success-50 dark:bg-success-700/20 border border-success-500/20 text-success-600 text-xs font-bold flex items-center space-x-2">
            <Check className="w-4 h-4" />
            <span>{message}</span>
          </div>
        )}

        {error && (
          <div className="p-3 rounded-xl bg-error-50 dark:bg-error-700/20 border border-error-500/20 text-error-500 text-xs font-bold flex items-center space-x-2">
            <AlertCircle className="w-4 h-4" />
            <span>{error}</span>
          </div>
        )}

        {/* Tab Buttons */}
        <div className="flex items-center space-x-2 p-1 rounded-2xl bg-slate-100 dark:bg-slate-700/50">
          <button
            onClick={() => setActiveTab('assign')}
            className={`flex-1 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center space-x-1.5 ${
              activeTab === 'assign'
                ? 'bg-white dark:bg-slate-800 text-brand-500 shadow-sm'
                : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
            }`}
          >
            <UserCheck className="w-4 h-4" />
            <span>{t('roleModal.assignTab')}</span>
          </button>

          <button
            onClick={() => setActiveTab('create')}
            className={`flex-1 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center space-x-1.5 ${
              activeTab === 'create'
                ? 'bg-white dark:bg-slate-800 text-brand-500 shadow-sm'
                : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
            }`}
          >
            <Plus className="w-4 h-4" />
            <span>{t('roleModal.createRoleTab')}</span>
          </button>
        </div>

        {loading ? (
          <div className="py-12 text-center text-xs font-bold text-slate-400 animate-pulse">
            {t('roleModal.loading')}
          </div>
        ) : (
          <>
            {/* Tab 1: Assign Role & Department to User */}
            {activeTab === 'assign' && (
              <form onSubmit={handleAssignRole} className="space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  {/* Select User */}
                  <div>
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                      {t('roleModal.selectEmployee')} *
                    </label>
                    <select
                      value={selectedUserId || ''}
                      onChange={(e) => setSelectedUserId(Number(e.target.value))}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                    >
                      {users.map((u) => (
                        <option key={u.id} value={u.id}>
                          {u.name} ({u.departmentName} - {u.roleName})
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Select Target Role */}
                  <div>
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                      {t('roleModal.assignRoleLabel')} *
                    </label>
                    <select
                      value={selectedRoleId || ''}
                      onChange={(e) => setSelectedRoleId(Number(e.target.value))}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                    >
                      {roles.map((r) => (
                        <option key={r.id} value={r.id}>
                          {r.name}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                {/* Organization Details (Department, Branch, Position) */}
                <div className="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 space-y-3">
                  <div className="text-xs font-extrabold text-slate-800 dark:text-slate-200 flex items-center space-x-1.5">
                    <Building className="w-4 h-4 text-brand-500" />
                    <span>{t('roleModal.orgSection')}</span>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    {/* Select Department */}
                    <div>
                      <label className="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">
                        {t('roleModal.department')}
                      </label>
                      <select
                        value={selectedDeptId || ''}
                        onChange={(e) => setSelectedDeptId(e.target.value ? Number(e.target.value) : null)}
                        className="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                      >
                        <option value="">{t('roleModal.selectDepartment')}</option>
                        {departments.map((d) => (
                          <option key={d.id} value={d.id}>
                            {d.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Select Branch */}
                    <div>
                      <label className="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">
                        {t('roleModal.branch')}
                      </label>
                      <select
                        value={selectedBranchId || ''}
                        onChange={(e) => setSelectedBranchId(e.target.value ? Number(e.target.value) : null)}
                        className="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                      >
                        <option value="">{t('roleModal.selectBranch')}</option>
                        {branches.map((b) => (
                          <option key={b.id} value={b.id}>
                            {b.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Select Position */}
                    <div>
                      <label className="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">
                        {t('roleModal.position')}
                      </label>
                      <select
                        value={selectedPosId || ''}
                        onChange={(e) => setSelectedPosId(e.target.value ? Number(e.target.value) : null)}
                        className="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                      >
                        <option value="">{t('roleModal.selectPosition')}</option>
                        {positions.map((p) => (
                          <option key={p.id} value={p.id}>
                            {p.name}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>
                </div>

                {/* Permissions Checkboxes */}
                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">
                    {t('roleModal.permissionsLabel')}
                  </label>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-40 overflow-y-auto p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700">
                    {permissions.map((p) => {
                      const isChecked = selectedPermIds.includes(p.id);
                      return (
                        <label
                          key={p.id}
                          className="flex items-center space-x-2.5 p-2 rounded-xl hover:bg-white dark:hover:bg-slate-800 transition-colors cursor-pointer text-xs font-semibold text-slate-800 dark:text-slate-200"
                        >
                          <input
                            type="checkbox"
                            checked={isChecked}
                            onChange={() => togglePerm(p.id)}
                            className="w-4 h-4 rounded text-brand-500 focus:ring-brand-500 border-slate-300"
                          />
                          <div>
                            <span className="font-bold">{p.name}</span>
                            {p.description && (
                              <span className="block text-[10px] text-slate-400 font-normal">
                                {p.description}
                              </span>
                            )}
                          </div>
                        </label>
                      );
                    })}
                  </div>
                </div>

                <div className="pt-4 border-t border-slate-100 dark:border-slate-700 flex justify-end space-x-3">
                  <button
                    type="button"
                    onClick={onClose}
                    className="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold text-xs hover:bg-slate-50 transition-colors"
                  >
                    {t('roleModal.close')}
                  </button>

                  <button
                    type="submit"
                    disabled={saving}
                    className="px-6 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 text-white font-bold text-xs shadow-md transition-all disabled:opacity-50 cursor-pointer"
                  >
                    {saving ? t('roleModal.saving') : t('roleModal.saveAssign')}
                  </button>
                </div>
              </form>
            )}

            {/* Tab 2: Create Role */}
            {activeTab === 'create' && (
              <form onSubmit={handleCreateRole} className="space-y-4">
                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    {t('roleModal.roleName')} *
                  </label>
                  <input
                    type="text"
                    value={newRoleName}
                    onChange={(e) => setNewRoleName(e.target.value)}
                    placeholder={t('roleModal.roleNamePlaceholder')}
                    className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500"
                    required
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    {t('roleModal.description')}
                  </label>
                  <textarea
                    rows={2}
                    value={newRoleDesc}
                    onChange={(e) => setNewRoleDesc(e.target.value)}
                    placeholder={t('roleModal.descriptionPlaceholder')}
                    className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500"
                  />
                </div>

                <div className="pt-4 border-t border-slate-100 dark:border-slate-700 flex justify-end space-x-3">
                  <button
                    type="button"
                    onClick={onClose}
                    className="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold text-xs hover:bg-slate-50 transition-colors"
                  >
                    {t('roleModal.close')}
                  </button>

                  <button
                    type="submit"
                    disabled={saving}
                    className="px-6 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 text-white font-bold text-xs shadow-md transition-all disabled:opacity-50 cursor-pointer"
                  >
                    {saving ? t('roleModal.creating') : t('roleModal.createRoleTab')}
                  </button>
                </div>
              </form>
            )}
          </>
        )}
      </div>
    </div>
  );
};

