import React, { useCallback, useEffect, useRef, useState } from 'react';
import { FileText, Loader2, ScanFace, Search, Upload, UserRound } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useToastStore } from '@/shared/presentation/store/useToastStore';
import { useT } from '@/shared/presentation/i18n/i18n';
import { EmptyState } from '@/shared/presentation/components/EmptyState';
import { RequiredMark } from '@/shared/presentation/components/RequiredMark';

interface FaceIdRecord {
  id: number;
  pinfl: string;
  full_name: string;
  last_name: string | null;
  first_name: string | null;
  birth_date: string | null;
  has_photo: boolean;
  has_document: boolean;
  created_at: string | null;
}

/** Laborlaw dasturidan avtomatik tushadigan so'rov. */
interface IncomingRequest {
  pinfl: string;
  first_name: string | null;
  last_name: string | null;
  middle_name: string | null;
  birth_date: string | null;
}

/** PINFL — qat'iy 14 raqam. */
const PINFL_LENGTH = 14;

/**
 * Ichki xavfsizlik → FaceID.
 *
 * Oqim: PINFL kiritiladi -> ism/familiya HR API'dan, tug'ilgan sana PINFL
 * raqamining o'zidan to'ldiriladi -> rasm va PDF biriktiriladi -> Tasdiqlash.
 * To'ldirilgan maydonlar tahrirlanadi: HR API xodimni topa olmasa ham yozuvni
 * qo'lda kiritib davom etish mumkin.
 */
export const FaceIdPage: React.FC = () => {
  const t = useT();
  const toast = useToastStore();

  const [pinfl, setPinfl] = useState('');
  const [lastName, setLastName] = useState('');
  const [firstName, setFirstName] = useState('');
  const [middleName, setMiddleName] = useState('');
  const [birthDate, setBirthDate] = useState('');

  const [photo, setPhoto] = useState<File | null>(null);
  const [photoPreview, setPhotoPreview] = useState<string | null>(null);
  const [document, setDocument] = useState<File | null>(null);

  const [isLooking, setIsLooking] = useState(false);
  const [isSaving, setIsSaving] = useState(false);

  const [records, setRecords] = useState<FaceIdRecord[]>([]);
  const [search, setSearch] = useState('');
  // Yozuv kiritilgan vaqt bo'yicha oraliq.
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');

  // Laborlaw dan kelib tushgan so'rovlar. Integratsiya ulanmagani uchun
  // ro'yxat hozircha bo'sh — API tayyor bo'lganda shu holat to'ldiriladi.
  const [incoming] = useState<IncomingRequest[]>([]);

  const photoInputRef = useRef<HTMLInputElement>(null);
  const documentInputRef = useRef<HTMLInputElement>(null);

  const loadRecords = useCallback(async (query: string, fromAt: string, toAt: string) => {
    try {
      const res = await axiosClient.get<{ data: FaceIdRecord[] }>('/face-id', {
        params: {
          ...(query ? { search: query } : {}),
          ...(fromAt ? { from: fromAt } : {}),
          ...(toAt ? { to: toAt } : {}),
        },
      });
      setRecords(res.data?.data ?? []);
    } catch {
      // Ro'yxat yuklanmasa forma baribir ishlayveradi.
      setRecords([]);
    }
  }, []);

  useEffect(() => {
    const id = setTimeout(() => void loadRecords(search.trim(), from, to), 300);

    return () => clearTimeout(id);
  }, [search, from, to, loadRecords]);

  // Ko'rib turilgan rasm uchun ajratilgan URL bo'shatiladi.
  useEffect(() => {
    if (!photo) {
      setPhotoPreview(null);

      return;
    }

    const url = URL.createObjectURL(photo);
    setPhotoPreview(url);

    return () => URL.revokeObjectURL(url);
  }, [photo]);

  const lookup = async () => {
    if (pinfl.length !== PINFL_LENGTH) {
      toast.error(t('faceId.pinflLength'));

      return;
    }

    setIsLooking(true);
    try {
      const res = await axiosClient.post<{
        data: {
          first_name: string | null;
          last_name: string | null;
          middle_name: string | null;
          birth_date: string | null;
          found: boolean;
        };
      }>('/face-id/lookup', { pinfl });

      const data = res.data.data;
      // Sana PINFL dan hisoblanadi — xodim topilmasa ham keladi.
      if (data.birth_date) setBirthDate(data.birth_date);
      if (data.last_name) setLastName(data.last_name);
      if (data.first_name) setFirstName(data.first_name);
      if (data.middle_name) setMiddleName(data.middle_name);

      if (!data.found) toast.error(t('faceId.notFound'));
    } catch (error: any) {
      toast.error(error?.response?.data?.message || t('faceId.lookupFailed'));
    } finally {
      setIsLooking(false);
    }
  };

  /** "FaceID qo'shish" — kelib tushgan ma'lumot yuqoridagi formaga ko'chiriladi. */
  const fillFromIncoming = (item: IncomingRequest) => {
    setPinfl(item.pinfl);
    setLastName(item.last_name ?? '');
    setFirstName(item.first_name ?? '');
    setMiddleName(item.middle_name ?? '');
    setBirthDate(item.birth_date ?? '');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const reset = () => {
    setPinfl('');
    setLastName('');
    setFirstName('');
    setMiddleName('');
    setBirthDate('');
    setPhoto(null);
    setDocument(null);
    if (photoInputRef.current) photoInputRef.current.value = '';
    if (documentInputRef.current) documentInputRef.current.value = '';
  };

  const submit = async () => {
    if (pinfl.length !== PINFL_LENGTH) {
      toast.error(t('faceId.pinflLength'));

      return;
    }

    setIsSaving(true);
    try {
      const form = new FormData();
      form.append('pinfl', pinfl);
      form.append('last_name', lastName);
      form.append('first_name', firstName);
      if (middleName) form.append('middle_name', middleName);
      if (birthDate) form.append('birth_date', birthDate);
      if (photo) form.append('photo', photo);
      if (document) form.append('document', document);

      await axiosClient.post('/face-id', form);
      toast.success(t('faceId.saved'));
      reset();
      await loadRecords(search.trim(), from, to);
    } catch (error: any) {
      toast.error(error?.response?.data?.message || t('faceId.saveFailed'));
    } finally {
      setIsSaving(false);
    }
  };

  const canSubmit = pinfl.length === PINFL_LENGTH && lastName.trim() !== '' && firstName.trim() !== '' && !isSaving;

  const field = 'w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 text-slate-900 dark:text-slate-100 text-sm font-semibold outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500';
  const label = 'block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5';
  const inlineLabel = 'text-xs font-bold text-slate-500 dark:text-slate-400';

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6">
      <header className="flex items-center gap-3">
        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300">
          <ScanFace className="h-6 w-6" />
        </span>
        <div className="min-w-0">
          <h1 className="truncate text-xl font-extrabold text-slate-900 dark:text-slate-100">{t('faceId.title')}</h1>
          <p className="truncate text-xs font-semibold text-slate-500 dark:text-slate-400">{t('faceId.subtitle')}</p>
        </div>
      </header>

      {/* Yangi yozuv: chapda rasm, o'ngda ma'lumotlar, pastda tugmalar. */}
      <section className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 p-4 sm:p-6">
        <h2 className="text-sm font-extrabold text-slate-800 dark:text-slate-100 mb-4">{t('faceId.newRecord')}</h2>

        <div className="grid gap-6 md:grid-cols-[200px,1fr]">
          {/* Rasm */}
          <div className="space-y-2">
            <span className={label}>{t('faceId.photo')}</span>
            <div className="aspect-[3/4] w-full max-w-[200px] overflow-hidden rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 flex items-center justify-center">
              {photoPreview ? (
                <img src={photoPreview} alt={t('faceId.photo')} className="h-full w-full object-cover" />
              ) : (
                <UserRound className="h-12 w-12 text-slate-300 dark:text-slate-600" />
              )}
            </div>
            <input
              ref={photoInputRef}
              type="file"
              /* Backend ham aynan shu ikki turni qabul qiladi. Ilgari bu yerda
                 `image/*` turardi: foydalanuvchi webp yoki gif tanlar, keyin
                 saqlashda tushunarsiz xato olardi. */
              accept="image/jpeg,image/png"
              className="hidden"
              onChange={(e) => setPhoto(e.target.files?.[0] ?? null)}
            />
            <button
              type="button"
              onClick={() => photoInputRef.current?.click()}
              className="inline-flex w-full max-w-[200px] items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2 text-xs font-bold text-slate-600 dark:text-slate-300 hover:border-rose-300 dark:hover:border-rose-700"
            >
              <Upload className="h-3.5 w-3.5" />
              {t('faceId.uploadPhoto')}
            </button>
            <p className="max-w-[200px] text-xs font-semibold text-slate-400">{t('faceId.photoHint')}</p>
          </div>

          {/* Ma'lumotlar */}
          <div className="space-y-4">
            <div>
              <label className={label} htmlFor="faceid-pinfl">
                {t('faceId.pinfl')} <RequiredMark />
              </label>
              <div className="flex gap-2">
                <input
                  id="faceid-pinfl"
                  value={pinfl}
                  onChange={(e) => setPinfl(e.target.value.replace(/\D/g, '').slice(0, PINFL_LENGTH))}
                  inputMode="numeric"
                  placeholder="00000000000000"
                  className={field}
                />
                <button
                  type="button"
                  onClick={lookup}
                  disabled={isLooking || pinfl.length !== PINFL_LENGTH}
                  className="inline-flex shrink-0 items-center gap-2 rounded-xl bg-rose-500 px-4 py-2 text-xs font-bold text-white hover:bg-rose-600 disabled:opacity-50"
                >
                  {isLooking ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
                  {t('faceId.lookup')}
                </button>
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className={label} htmlFor="faceid-last">
                  {t('faceId.lastName')} <RequiredMark />
                </label>
                <input id="faceid-last" value={lastName} onChange={(e) => setLastName(e.target.value)} className={field} />
              </div>
              <div>
                <label className={label} htmlFor="faceid-first">
                  {t('faceId.firstName')} <RequiredMark />
                </label>
                <input id="faceid-first" value={firstName} onChange={(e) => setFirstName(e.target.value)} className={field} />
              </div>
              <div>
                <label className={label} htmlFor="faceid-middle">{t('faceId.middleName')}</label>
                <input id="faceid-middle" value={middleName} onChange={(e) => setMiddleName(e.target.value)} className={field} />
              </div>
              <div>
                <label className={label} htmlFor="faceid-birth">{t('faceId.birthDate')}</label>
                <input id="faceid-birth" type="date" value={birthDate} onChange={(e) => setBirthDate(e.target.value)} className={field} />
              </div>
            </div>
          </div>
        </div>

        {/* Pastki qator: PDF yuklash — chapda, Tasdiqlash — o'ngda. */}
        <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 dark:border-slate-800 pt-4">
          <div className="flex items-center gap-2">
            <input
              ref={documentInputRef}
              type="file"
              accept="application/pdf"
              className="hidden"
              onChange={(e) => setDocument(e.target.files?.[0] ?? null)}
            />
            <button
              type="button"
              onClick={() => documentInputRef.current?.click()}
              className="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-xs font-bold text-slate-600 dark:text-slate-300 hover:border-rose-300 dark:hover:border-rose-700"
            >
              <FileText className="h-4 w-4" />
              {t('faceId.uploadPdf')}
            </button>
            {document && (
              <span className="max-w-[220px] truncate text-xs font-semibold text-slate-500 dark:text-slate-400">
                {document.name}
              </span>
            )}
          </div>

          <button
            type="button"
            onClick={submit}
            disabled={!canSubmit}
            className="inline-flex items-center gap-2 rounded-xl bg-rose-500 px-6 py-2.5 text-xs font-bold text-white hover:bg-rose-600 disabled:opacity-50"
          >
            {isSaving && <Loader2 className="h-4 w-4 animate-spin" />}
            {t('faceId.submit')}
          </button>
        </div>
      </section>

      {/* Laborlaw dasturidan kelib tushadigan so'rovlar.
          Integratsiya hali yo'q — ro'yxat bo'sh turadi. Ma'lumot manbai
          ulangach shu yerga tushadi va "FaceID qo'shish" tugmasi yuqoridagi
          formani o'sha ma'lumot bilan to'ldiradi. */}
      <section className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 p-4 sm:p-6">
        <h2 className="text-sm font-extrabold text-slate-800 dark:text-slate-100">{t('faceId.incoming')}</h2>
        <p className="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">{t('faceId.incomingHint')}</p>

        {incoming.length === 0 ? (
          <div className="mt-4 rounded-2xl border border-dashed border-slate-300 p-8 text-center dark:border-slate-700">
            <p className="text-sm font-semibold text-slate-500 dark:text-slate-400">{t('faceId.empty')}</p>
          </div>
        ) : (
          <div className="mt-4 space-y-2">
            {incoming.map((item) => (
              <div
                key={item.pinfl}
                className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 p-3 dark:border-slate-700"
              >
                <span className="min-w-0">
                  <span className="block truncate text-sm font-bold text-slate-800 dark:text-slate-100">
                    {[item.last_name, item.first_name].filter(Boolean).join(' ')}
                  </span>
                  <span className="block font-mono text-[11px] text-slate-400">{item.pinfl}</span>
                </span>
                <button
                  type="button"
                  onClick={() => fillFromIncoming(item)}
                  className="inline-flex items-center gap-2 rounded-xl bg-rose-500 px-4 py-2 text-xs font-bold text-white hover:bg-rose-600"
                >
                  <ScanFace className="h-4 w-4" />
                  {t('faceId.addFaceId')}
                </button>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Saqlangan yozuvlar */}
      <section className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 p-4 sm:p-6">
        <div className="mb-4 space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-sm font-extrabold text-slate-800 dark:text-slate-100">{t('faceId.records')}</h2>
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder={t('faceId.search')}
                className={`${field} pl-9 sm:w-72`}
              />
            </div>
          </div>

          {/* Kiritilgan vaqt bo'yicha "dan — gacha". Bo'sh chegara cheklamaydi. */}
          <div className="flex flex-wrap items-center gap-2">
            <label className={inlineLabel} htmlFor="faceid-from">{t('permitReq.dateFrom')}</label>
            <input
              id="faceid-from"
              type="datetime-local"
              value={from}
              onChange={(e) => setFrom(e.target.value)}
              className={`${field} sm:w-56`}
            />
            <label className={inlineLabel} htmlFor="faceid-to">{t('permitReq.dateTo')}</label>
            <input
              id="faceid-to"
              type="datetime-local"
              value={to}
              onChange={(e) => setTo(e.target.value)}
              className={`${field} sm:w-56`}
            />
            {(from || to) && (
              <button
                type="button"
                onClick={() => { setFrom(''); setTo(''); }}
                className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:border-rose-300 dark:border-slate-700 dark:text-slate-300 dark:hover:border-rose-700"
              >
                {t('permitReq.dateClear')}
              </button>
            )}
          </div>
        </div>

        {records.length === 0 ? (
          <EmptyState title={t('faceId.empty')} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[640px] text-left text-sm">
              <thead>
                <tr className="text-xs font-bold uppercase tracking-wide text-slate-400">
                  <th className="pb-2">{t('faceId.lastName')}</th>
                  <th className="pb-2">{t('faceId.pinfl')}</th>
                  <th className="pb-2">{t('faceId.birthDate')}</th>
                  <th className="pb-2 text-center">{t('faceId.photo')}</th>
                  <th className="pb-2 text-center">PDF</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {records.map((record) => (
                  <tr key={record.id} className="text-slate-700 dark:text-slate-200">
                    <td className="py-2.5 font-semibold">{record.full_name}</td>
                    <td className="py-2.5 font-mono text-xs">{record.pinfl}</td>
                    <td className="py-2.5">{record.birth_date ?? '—'}</td>
                    <td className="py-2.5 text-center">{record.has_photo ? '🖼' : '—'}</td>
                    <td className="py-2.5 text-center">{record.has_document ? '📄' : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
};
