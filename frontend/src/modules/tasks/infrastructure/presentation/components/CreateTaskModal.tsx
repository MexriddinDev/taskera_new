import React, { useState, useEffect, useRef } from 'react';
import { X, Send, AlertCircle, UsersRound, Paperclip, Mic, Square, Image, FileText, Trash2, FileText as TemplateIcon } from 'lucide-react';
import { useCreateTask } from '../hooks/useCreateTask';
import { TaskPriority } from '../../../domain/entities/Task';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';
import fixWebmDuration from 'fix-webm-duration';

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
  const t = useT();
  const [todo, setTodo] = useState('');
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
  // Yuboriladigan YAKUNIY blob — davomiyligi tuzatilgan holda shu yerda turadi.
  const audioBlobRef = useRef<Blob | null>(null);
  const recordStartRef = useRef<number>(0);
  // Yozuv uzunligi — yozilayotganda tirik hisoblagich, tugagach yakuniy qiymat.
  const [recordedMs, setRecordedMs] = useState(0);
  const streamRef = useRef<MediaStream | null>(null);

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

  // Yozilayotganda uzunlikni tirik ko'rsatamiz — foydalanuvchi ham, biz ham
  // yozuv necha soniya davom etganini aniq bilamiz.
  useEffect(() => {
    if (!isRecording) return;

    const id = window.setInterval(
      () => setRecordedMs(Date.now() - recordStartRef.current),
      200,
    );

    return () => window.clearInterval(id);
  }, [isRecording]);

  if (!isOpen) return null;

  const formatDuration = (ms: number) => {
    const total = Math.round(ms / 1000);
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
  };

  // Fayl qabul qilishning yagona nuqtasi — tugma orqali tanlash ham,
  // Ctrl+V bilan yopishtirish ham shu yerdan o'tadi.
  const acceptFile = (file: File) => {
    setAttachedFile(file);
    setFilePreview((old) => {
      if (old) URL.revokeObjectURL(old);
      return file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
    });
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      acceptFile(e.target.files[0]);
    }
  };

  // Ctrl+V: ekran rasmini to'g'ridan-to'g'ri yopishtirish.
  // Clipboard'dan kelgan faylning nomi bo'lmaydi ("image.png" yoki bo'sh),
  // shuning uchun vaqt belgisi bilan tushunarli nom beramiz — zayavkada
  // biriktirma nomi shu ko'rinishda saqlanadi.
  const handlePaste = (e: React.ClipboardEvent) => {
    const items = Array.from(e.clipboardData?.items ?? []);
    const imageItem = items.find((item) => item.kind === 'file' && item.type.startsWith('image/'));

    if (!imageItem) {
      return; // oddiy matn yopishtirilyapti — aralashmaymiz
    }

    const file = imageItem.getAsFile();
    if (!file) {
      return;
    }

    e.preventDefault();

    const ext = (file.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
    const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
    acceptFile(new File([file], `screenshot_${stamp}.${ext}`, { type: file.type }));
    setError(null);
  };

  const startVoiceRecording = async () => {
    // Brauzer mikrofonni faqat "secure context" da beradi: HTTPS yoki
    // localhost/127.0.0.1. Oddiy HTTP orqali LAN IP bilan ochilganda
    // (masalan http://172.28.201.27:5173) navigator.mediaDevices umuman
    // mavjud bo'lmaydi — bu ruxsat rad etilgani emas, boshqa muammo.
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
      setError(t('createTask.micInsecureContext'));
      return;
    }

    if (typeof MediaRecorder === 'undefined') {
      setError(t('createTask.micUnsupported'));
      return;
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      // Stream ref'da saqlanadi — yozuv tugaganda/modal yopilganda track'lar
      // to'xtatiladi (aks holda mikrofon brauzerda yoniq qoladi — privacy!)
      streamRef.current = stream;
      const mediaRecorder = new MediaRecorder(stream);
      mediaRecorderRef.current = mediaRecorder;
      audioChunksRef.current = [];

      mediaRecorder.ondataavailable = (event) => {
        if (event.data.size > 0) {
          audioChunksRef.current.push(event.data);
        }
      };

      mediaRecorder.onstop = async () => {
        const rawBlob = new Blob(audioChunksRef.current, { type: 'audio/webm' });

        // MediaRecorder WebM'ni "jonli oqim" sifatida yozadi va sarlavhaga
        // davomiylikni QO'YMAYDI. Natijada <audio> duration'ni Infinity deb
        // ko'radi va yozuv bir necha soniyada tugagandek eshitiladi —
        // fayl to'liq bo'lsa ham. Shu yerda haqiqiy davomiylikni yozib qo'yamiz.
        const durationMs = Date.now() - recordStartRef.current;
        setRecordedMs(durationMs);
        let finalBlob = rawBlob;
        try {
          finalBlob = await fixWebmDuration(rawBlob, durationMs, { logger: false });
        } catch (e) {
          console.error("WebM davomiyligini tuzatib bolmadi, xom yozuv ishlatiladi", e);
        }

        audioBlobRef.current = finalBlob;
        const url = URL.createObjectURL(finalBlob);
        setAudioUrl((old) => {
          if (old) URL.revokeObjectURL(old);
          return url;
        });
        stopStreamTracks();
      };

      recordStartRef.current = Date.now();
      // Timeslice: ma'lumot har soniyada `ondataavailable` ga tashlanadi.
      // Timeslice'siz butun yozuv faqat stop paytida bitta bo'lakda keladi va
      // biror uzilishda (sahifa qayta render bo'lishi, oqim uzilishi) hammasi
      // yo'qoladi yoki qirqilib qoladi.
      mediaRecorder.start(1000);
      setIsRecording(true);
      setRecordedMs(0);
    } catch (err) {
      // Asl sababni ko'rsatamiz — ilgari har qanday xato "ruxsat berilmadi"
      // deb chiqardi va muammoni topish imkonsiz edi.
      const name = (err as DOMException)?.name;
      if (name === 'NotAllowedError' || name === 'SecurityError') {
        setError(t('createTask.micPermission'));
      } else if (name === 'NotFoundError' || name === 'OverconstrainedError') {
        setError(t('createTask.micNotFound'));
      } else if (name === 'NotReadableError') {
        setError(t('createTask.micBusy'));
      } else {
        setError(t('createTask.micUnsupported'));
      }
      console.error('Mikrofonni ishga tushirib bo\'lmadi', err);
    }
  };

  const stopStreamTracks = () => {
    streamRef.current?.getTracks().forEach((track) => track.stop());
    streamRef.current = null;
  };

  const stopVoiceRecording = () => {
    if (mediaRecorderRef.current && isRecording) {
      mediaRecorderRef.current.stop();
      setIsRecording(false);
    }
  };

  // Modal yopilganda ham mikrofon va eski blob URL tozalanadi
  const handleClose = () => {
    if (isRecording) {
      try { mediaRecorderRef.current?.stop(); } catch { /* noop */ }
      setIsRecording(false);
    }
    stopStreamTracks();
    onClose();
  };

  // Yozilgan ovozni butunlay olib tashlaydi. audioChunksRef ni ham tozalash SHART —
  // handleSubmit aynan shu ref uzunligiga qarab audio biriktiradi, ya'ni faqat
  // audioUrl ni null qilish "o'chirdim" degani emas: fayl baribir yuborilaverardi.
  const clearRecording = () => {
    audioChunksRef.current = [];
    audioBlobRef.current = null;
    setRecordedMs(0);
    setAudioUrl((old) => {
      if (old) URL.revokeObjectURL(old);
      return null;
    });
  };

  const removeAttachedFile = () => {
    setAttachedFile(null);
    setFilePreview((old) => {
      if (old) URL.revokeObjectURL(old);
      return null;
    });
  };

  const resetForm = () => {
    setTodo('');
    removeAttachedFile();
    clearRecording();
    setPriority('medium');
    setSelectedTeamId(null);
    setTemplates([]);
    setSelectedTemplateId(null);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!selectedTeamId) {
      setError(t('createTask.teamRequired'));
      return;
    }

    let fullDescription = todo.trim();

    if (!fullDescription) {
      setError(t('createTask.todoRequired'));
      return;
    }

    const selectedTeam = teams.find((t) => t.id === selectedTeamId);

    setError(null);

    const formData = new FormData();
    formData.append('todo', fullDescription);
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

    // Xom chunk'lardan emas, davomiyligi tuzatilgan blobdan yuboramiz.
    if (audioBlobRef.current) {
      const audioFile = new File([audioBlobRef.current], `voice_${Date.now()}.webm`, { type: 'audio/webm' });
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
          const msg = err.response?.data?.message || err.message || t('createTask.createError');
          setError(msg);
        },
      }
    );
  };

  return (
    <div
      onPaste={handlePaste}
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn"
    >
      <div className="bg-white dark:bg-slate-800 rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-6 relative overflow-hidden max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700">
          <div>
            <h2 className="text-xl font-extrabold text-slate-900 dark:text-slate-100">{t('createTask.title')}</h2>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              {t('createTask.subtitle')}
            </p>
          </div>
          <button
            onClick={handleClose}
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
              <span>{t('createTask.teamLabel')}</span>
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
                {teamsLoading ? t('createTask.teamsLoading') : t('createTask.selectTeam')}
              </option>
              {teams.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name}
                </option>
              ))}
            </select>
            {!teamsLoading && teams.length === 0 && (
              <p className="mt-1.5 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                {t('createTask.noTeams')}
              </p>
            )}
          </div>

          {/* Template Selection — tanlangan guruhga mos shablonlar */}
          {selectedTeamId !== null && (
            <div>
              <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center space-x-1">
                <TemplateIcon className="w-4 h-4 text-brand-500" />
                <span>{t('createTask.templateLabel')}</span>
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
                    ? t('createTask.templatesLoading')
                    : templates.length === 0
                      ? t('createTask.noTemplates')
                      : t('createTask.selectTemplate')}
                </option>
                {templates.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
              </select>
              {!templatesLoading && templates.length > 0 && (
                <p className="mt-1.5 text-[11px] font-semibold text-slate-400">
                  {t('createTask.templateHint')}
                </p>
              )}
            </div>
          )}

          {/* Main Description */}
          <div>
            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              {t('createTask.todoLabel')}
            </label>
            <textarea
              rows={4}
              value={todo}
              onChange={(e) => setTodo(e.target.value)}
              placeholder={t('createTask.todoPlaceholder')}
              className="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-slate-100 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none transition-all"
              required
            />
          </div>

          {/* Media Attachments (Photo/Video & Voice Recording) */}
          <div className="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 space-y-3">
            <div className="text-xs font-extrabold text-slate-700 dark:text-slate-300 flex items-center justify-between">
              <span>{t('createTask.mediaTitle')}</span>
              <Paperclip className="w-4 h-4 text-slate-400" />
            </div>

            <p className="text-[11px] font-semibold text-slate-400">
              {t('createTask.pasteHint')}
            </p>

            <div className="flex flex-wrap items-center gap-3">
              {/* Image/File Input */}
              <label className="cursor-pointer inline-flex items-center space-x-1.5 px-3 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-slate-100 transition-colors">
                <Image className="w-4 h-4 text-brand-500" />
                <span>{t('createTask.attachMedia')}</span>
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
                  <span>{t('createTask.recordVoice')}</span>
                </button>
              ) : (
                <button
                  type="button"
                  onClick={stopVoiceRecording}
                  className="inline-flex items-center space-x-1.5 px-3 py-2 rounded-xl bg-rose-600 text-white text-xs font-bold animate-pulse"
                >
                  <Square className="w-4 h-4" />
                  <span>{t('createTask.stopRecording')} · {formatDuration(recordedMs)}</span>
                </button>
              )}
            </div>

            {/* Attached File Preview */}
            {attachedFile && (
              <div className="flex items-center justify-between gap-2 text-xs font-bold text-slate-700 dark:text-slate-300">
                <div className="flex items-center space-x-2 min-w-0">
                  <FileText className="w-4 h-4 text-brand-500 flex-shrink-0" />
                  <span className="truncate">{t('createTask.attached', { name: attachedFile.name })}</span>
                </div>
                <button
                  type="button"
                  onClick={removeAttachedFile}
                  title={t('createTask.removeFile')}
                  aria-label={t('createTask.removeFile')}
                  className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors flex-shrink-0"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            )}

            {filePreview && (
              <img src={filePreview} alt="Preview" className="w-24 h-24 object-cover rounded-xl border border-slate-200" />
            )}

            {/* Audio Preview */}
            {audioUrl && (
              <div className="space-y-1">
                <div className="flex items-center justify-between gap-2">
                  <span className="text-[11px] font-bold text-slate-500">
                    {t('createTask.audioRecorded')}
                    {recordedMs > 0 && ` · ${formatDuration(recordedMs)}`}
                  </span>
                  <button
                    type="button"
                    onClick={clearRecording}
                    title={t('createTask.removeRecording')}
                    aria-label={t('createTask.removeRecording')}
                    className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-bold text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                    <span>{t('createTask.removeRecording')}</span>
                  </button>
                </div>
                <audio src={audioUrl} controls className="w-full h-8" />
              </div>
            )}
          </div>

          {/* Priority */}
          <div>
            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              {t('createTask.priorityLabel')}
            </label>
            <div className="grid grid-cols-3 gap-2">
              <button
                type="button"
                onClick={() => setPriority('low')}
                className={`py-2 rounded-xl text-xs font-bold border transition-all ${
                  priority === 'low'
                    ? 'bg-success-500 text-white border-success-500 shadow-sm'
                    : 'bg-white dark:bg-slate-800 text-slate-500 border-slate-200 dark:border-slate-700 hover:border-success-400'
                }`}
              >
                {t('priority.low')}
              </button>

              <button
                type="button"
                onClick={() => setPriority('medium')}
                className={`py-2 rounded-xl text-xs font-bold border transition-all ${
                  priority === 'medium'
                    ? 'bg-amber-400 text-white border-amber-400 shadow-sm'
                    : 'bg-white dark:bg-slate-800 text-slate-500 border-slate-200 dark:border-slate-700 hover:border-amber-300'
                }`}
              >
                {t('priority.medium')}
              </button>

              <button
                type="button"
                onClick={() => setPriority('high')}
                className={`py-2 rounded-xl text-xs font-bold border transition-all ${
                  priority === 'high'
                    ? 'bg-error-500 text-white border-error-500 shadow-sm'
                    : 'bg-white dark:bg-slate-800 text-slate-500 border-slate-200 dark:border-slate-700 hover:border-error-400'
                }`}
              >
                {t('priority.high')}
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
              {t('common.cancel')}
            </button>

            <button
              type="submit"
              disabled={createTaskMutation.isPending}
              className="inline-flex items-center space-x-2 px-6 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 text-white font-bold text-xs shadow-md transition-all disabled:opacity-50 cursor-pointer"
            >
              <span>{t('createTask.submit')}</span>
              <Send className="w-4 h-4" />
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
