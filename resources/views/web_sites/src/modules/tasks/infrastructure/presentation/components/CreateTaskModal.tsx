import React, { useState, useEffect, useRef } from 'react';
import { X, Send, AlertCircle, UsersRound, Paperclip, Mic, Square, Image, FileText, FileText as TemplateIcon } from 'lucide-react';
import { useCreateTask } from '../hooks/useCreateTask';
import { TaskPriority } from '../../../domain/entities/Task';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';

interface CreateTaskModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

interface TeamItem {
  id: number;
  name: string;
  code: string;
  department_id?: number | null;
}

interface TicketTemplate {
  id: number;
  teamId: number | null;
  name: string;
  content: string;
}

export const CreateTaskModal: React.FC<CreateTaskModalProps> = ({ isOpen, onClose, onSuccess }) => {
  const [todo, setTodo] = useState('');
  const [originDepartment, setOriginDepartment] = useState('');
  const [initiatorPhone, setInitiatorPhone] = useState('');
  const [priority, setPriority] = useState<TaskPriority>('medium');

  // Group / Team state — to'liq dinamik (/teams dan keladi)
  const [teams, setTeams] = useState<TeamItem[]>([]);
  const [selectedTeamId, setSelectedTeamId] = useState<number | null>(null);
  const [teamsLoading, setTeamsLoading] = useState(false);

  // Templates (Shablonlar) — tanlangan guruhga qarab yuklanadi
  const [templates, setTemplates] = useState<TicketTemplate[]>([]);
  const [templatesLoading, setTemplatesLoading] = useState(false);
  const [selectedTemplateId, setSelectedTemplateId] = useState<number | null>(null);

  // Media attachments & Voice Recording
  const [attachedFile, setAttachedFile] = useState<File | null>(null);
  const [filePreview, setFilePreview] = useState<string | null>(null);
  const [isRecording, setIsRecording] = useState(false);
  const [audioUrl, setAudioUrl] = useState<string | null>(null);
  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const audioChunksRef = useRef<Blob[]>([]);

  const [error, setError] = useState<string | null>(null);
  const createTaskMutation = useCreateTask();

  useEffect(() => {
    if (isOpen) {
      setTeamsLoading(true);
      axiosClient.get<{ data: TeamItem[] }>('/teams')
        .then((res) => {
          const list = res.data.data || [];
          setTeams(list);
          // Guruhni foydalanuvchi o'zi tanlaydi — avtomatik tanlab qo'ymaymiz.
        })
        .catch(() => {
          setTeams([]);
        })
        .finally(() => setTeamsLoading(false));
    }
  }, [isOpen]);

  // Tanlangan guruhga mos shablonlarni yuklash
  useEffect(() => {
    if (!selectedTeamId) {
      setTemplates([]);
      setSelectedTemplateId(null);
      return;
    }

    setTemplatesLoading(true);
    setSelectedTemplateId(null);
    axiosClient.get<{ data: TicketTemplate[] }>('/ticket-templates', { params: { team_id: selectedTeamId } })
      .then((res) => {
        setTemplates(res.data.data || []);
      })
      .catch(() => {
        setTemplates([]);
      })
      .finally(() => setTemplatesLoading(false));
  }, [selectedTeamId]);

  if (!isOpen) return null;

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0];
      setAttachedFile(file);
      if (file.type.startsWith('image/')) {
        setFilePreview(URL.createObjectURL(file));
      } else {
        setFilePreview(null);
      }
    }
  };

  const startVoiceRecording = async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const mediaRecorder = new MediaRecorder(stream);
      mediaRecorderRef.current = mediaRecorder;
      audioChunksRef.current = [];

      mediaRecorder.ondataavailable = (event) => {
        if (event.data.size > 0) {
          audioChunksRef.current.push(event.data);
        }
      };

      mediaRecorder.onstop = () => {
        const audioBlob = new Blob(audioChunksRef.current, { type: 'audio/webm' });
        const url = URL.createObjectURL(audioBlob);
        setAudioUrl(url);
      };

      mediaRecorder.start();
      setIsRecording(true);
    } catch (err) {
      setError('Mikrofondan foydalanishga ruxsat berilmadi');
    }
  };

  const stopVoiceRecording = () => {
    if (mediaRecorderRef.current && isRecording) {
      mediaRecorderRef.current.stop();
      setIsRecording(false);
    }
  };

  const resetForm = () => {
    setTodo('');
    setOriginDepartment('');
    setInitiatorPhone('');
    setAttachedFile(null);
    setFilePreview(null);
    setAudioUrl(null);
    setPriority('medium');
    setSelectedTeamId(null);
    setTemplates([]);
    setSelectedTemplateId(null);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!selectedTeamId) {
      setError('Zayavka boradigan guruhni tanlang');
      return;
    }

    let fullDescription = todo.trim();

    if (!fullDescription) {
      setError('Zayavka mazmuni yozilishi shart');
      return;
    }

    const selectedTeam = teams.find((t) => t.id === selectedTeamId);

    setError(null);

    const formData = new FormData();
    formData.append('todo', fullDescription);
    formData.append('originDepartment', originDepartment || 'Bosh ofis');
    if (initiatorPhone) formData.append('initiatorPhone', initiatorPhone);
    formData.append('priority', priority);
    if (selectedTeam?.name) formData.append('category', selectedTeam.name);
    formData.append('teamId', String(selectedTeamId));

    if (attachedFile) {
      if (attachedFile.type.startsWith('image/')) {
        formData.append('screenshot', attachedFile);
      } else if (attachedFile.type.startsWith('video/')) {
        formData.append('video', attachedFile);
      } else {
        formData.append('file', attachedFile);
      }
    }

    if (audioChunksRef.current.length > 0) {
      const audioBlob = new Blob(audioChunksRef.current, { type: 'audio/webm' });
      const audioFile = new File([audioBlob], `voice_${Date.now()}.webm`, { type: 'audio/webm' });
      formData.append('audio', audioFile);
    }

    createTaskMutation.mutate(
      formData,
      {
        onSuccess: () => {
          resetForm();
          onSuccess();
          onClose();
        },
        onError: (err: any) => {
          const msg = err.response?.data?.message || err.message || 'Zayavka yaratishda xatolik yuz berdi';
          setError(msg);
        },
      }
    );
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
      <div className="bg-white dark:bg-slate-800 rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-6 relative overflow-hidden max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700">
          <div>
            <h2 className="text-xl font-extrabold text-slate-900 dark:text-slate-100">Yangi Zayavka Yuborish</h2>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Zayavka boradigan guruhni tanlang va muammoni batafsil yozing
            </p>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {error && (
          <div className="p-3 rounded-xl bg-error-50 dark:bg-error-700/20 border border-error-500/20 text-error-500 text-xs font-semibold flex items-center space-x-2">
            <AlertCircle className="w-4 h-4 flex-shrink-0" />
            <span>{error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Service Group Selection — to'liq dinamik */}
          <div>
            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center space-x-1">
              <UsersRound className="w-4 h-4 text-brand-500" />
              <span>Zayavka boradigan guruh *</span>
            </label>
            <select
              value={selectedTeamId ?? ''}
              onChange={(e) => {
                const val = e.target.value;
                setSelectedTeamId(val ? Number(val) : null);
              }}
              disabled={teamsLoading}
              className="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-slate-100 text-sm font-extrabold focus:ring-2 focus:ring-brand-500 focus:outline-none transition-all disabled:opacity-60"
            >
              <option value="">
                {teamsLoading ? 'Guruhlar yuklanmoqda...' : '-- Guruhni tanlang --'}
              </option>
              {teams.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name}
                </option>
              ))}
            </select>
            {!teamsLoading && teams.length === 0 && (
              <p className="mt-1.5 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                Hozircha guruhlar mavjud emas. Administrator guruh qo'shishi kerak.
              </p>
            )}
          </div>

          {/* Template Selection — tanlangan guruhga mos shablonlar */}
          {selectedTeamId !== null && (
            <div>
              <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center space-x-1">
                <TemplateIcon className="w-4 h-4 text-brand-500" />
                <span>Shablon tanlash (ixtiyoriy)</span>
              </label>
              <select
                value={selectedTemplateId ?? ''}
                onChange={(e) => {
                  const val = e.target.value;
                  const id = val ? Number(val) : null;
                  setSelectedTemplateId(id);
                  const tmpl = templates.find((t) => t.id === id);
                  if (tmpl) {
                    setTodo(tmpl.content);
                  }
                }}
                disabled={templatesLoading || templates.length === 0}
                className="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-slate-100 text-sm font-extrabold focus:ring-2 focus:ring-brand-500 focus:outline-none transition-all disabled:opacity-60"
              >
                <option value="">
                  {templatesLoading
                    ? 'Shablonlar yuklanmoqda...'
                    : templates.length === 0
                      ? 'Bu guruh uchun shablonlar yo\'q'
                      : '-- Shablonni tanlang --'}
                </option>
                {templates.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
              </select>
              {!templatesLoading && templates.length > 0 && (
                <p className="mt-1.5 text-[11px] font-semibold text-slate-400">
                  Shablon tanlasangiz matn avtomatik to'ldiriladi — ustiga o'z so'zlaringizni qo'shishingiz mumkin.
                </p>
              )}
            </div>
          )}

          {/* Main Description */}
          <div>
            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              Zayavka mazmuni va muammo batafsil *
            </label>
            <textarea
              rows={4}
              value={todo}
              onChange={(e) => setTodo(e.target.value)}
              placeholder="Zayavka yoki muammo tafsilotlarini yozing..."
              className="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-slate-100 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none transition-all"
              required
            />
          </div>

          {/* Media Attachments (Photo/Video & Voice Recording) */}
          <div className="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 space-y-3">
            <div className="text-xs font-extrabold text-slate-700 dark:text-slate-300 flex items-center justify-between">
              <span>Rasm, Video va Ovozli Xabar (ixtiyoriy)</span>
              <Paperclip className="w-4 h-4 text-slate-400" />
            </div>

            <div className="flex flex-wrap items-center gap-3">
              {/* Image/File Input */}
              <label className="cursor-pointer inline-flex items-center space-x-1.5 px-3 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-slate-100 transition-colors">
                <Image className="w-4 h-4 text-brand-500" />
                <span>Rasm / Video biriktirish</span>
                <input
                  type="file"
                  accept="image/*,video/*"
                  onChange={handleFileChange}
                  className="hidden"
                />
              </label>

              {/* Voice Record Button */}
              {!isRecording ? (
                <button
                  type="button"
                  onClick={startVoiceRecording}
                  className="inline-flex items-center space-x-1.5 px-3 py-2 rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800 text-xs font-bold hover:bg-rose-100 transition-colors"
                >
                  <Mic className="w-4 h-4" />
                  <span>Ovoz yozish</span>
                </button>
              ) : (
                <button
                  type="button"
                  onClick={stopVoiceRecording}
                  className="inline-flex items-center space-x-1.5 px-3 py-2 rounded-xl bg-rose-600 text-white text-xs font-bold animate-pulse"
                >
                  <Square className="w-4 h-4" />
                  <span>To'xtatish (Yozilmoqda...)</span>
                </button>
              )}
            </div>

            {/* Attached File Preview */}
            {attachedFile && (
              <div className="flex items-center space-x-2 text-xs font-bold text-slate-700 dark:text-slate-300">
                <FileText className="w-4 h-4 text-brand-500" />
                <span>Biriktirildi: {attachedFile.name}</span>
              </div>
            )}

            {filePreview && (
              <img src={filePreview} alt="Preview" className="w-24 h-24 object-cover rounded-xl border border-slate-200" />
            )}

            {/* Audio Preview */}
            {audioUrl && (
              <div className="space-y-1">
                <span className="text-[11px] font-bold text-slate-500">Ovozli xabar yozildi:</span>
                <audio src={audioUrl} controls className="w-full h-8" />
              </div>
            )}
          </div>

          {/* Department & Contact */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                Bo'lim / Xona
              </label>
              <input
                type="text"
                value={originDepartment}
                onChange={(e) => setOriginDepartment(e.target.value)}
                placeholder="Buxgalteriya, 304-xona"
                className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-slate-100 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none transition-all"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                Telefon raqam
              </label>
              <input
                type="text"
                value={initiatorPhone}
                onChange={(e) => setInitiatorPhone(e.target.value)}
                placeholder="+998 90 123-45-67"
                className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-slate-100 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none transition-all"
              />
            </div>
          </div>

          {/* Priority */}
          <div>
            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              Ustuvorlik darajasi (Priority)
            </label>
            <div className="grid grid-cols-3 gap-2">
              <button
                type="button"
                onClick={() => setPriority('low')}
                className={`py-2 rounded-xl text-xs font-bold border transition-all ${
                  priority === 'low'
                    ? 'bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border-slate-400'
                    : 'bg-white dark:bg-slate-800 text-slate-500 border-slate-200 dark:border-slate-700'
                }`}
              >
                Past (Low)
              </button>

              <button
                type="button"
                onClick={() => setPriority('medium')}
                className={`py-2 rounded-xl text-xs font-bold border transition-all ${
                  priority === 'medium'
                    ? 'bg-amber-500 text-white border-amber-500 shadow-sm'
                    : 'bg-white dark:bg-slate-800 text-slate-500 border-slate-200 dark:border-slate-700'
                }`}
              >
                O'rta (Medium)
              </button>

              <button
                type="button"
                onClick={() => setPriority('high')}
                className={`py-2 rounded-xl text-xs font-bold border transition-all ${
                  priority === 'high'
                    ? 'bg-orange-500 text-white border-orange-500 shadow-sm'
                    : 'bg-white dark:bg-slate-800 text-slate-500 border-slate-200 dark:border-slate-700'
                }`}
              >
                Yuqori (High)
              </button>
            </div>
          </div>

          {/* Footer Actions */}
          <div className="pt-4 flex items-center justify-end space-x-3 border-t border-slate-100 dark:border-slate-700">
            <button
              type="button"
              onClick={onClose}
              className="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold text-xs hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
            >
              Bekor qilish
            </button>

            <button
              type="submit"
              disabled={createTaskMutation.isPending}
              className="inline-flex items-center space-x-2 px-6 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 text-white font-bold text-xs shadow-md transition-all disabled:opacity-50 cursor-pointer"
            >
              <span>Yuborish</span>
              <Send className="w-4 h-4" />
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
