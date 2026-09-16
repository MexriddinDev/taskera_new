import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { buildTaskStepper } from './taskStepper.ts';

const keys = (status: string | null, completed?: boolean, rating?: number | null) =>
  buildTaskStepper(status, completed, rating).steps.map((step) => step.key);

const current = (status: string | null, completed?: boolean, rating?: number | null) => {
  const { steps, currentIndex } = buildTaskStepper(status, completed, rating);

  return steps[currentIndex].key;
};

test("bajarilgan zayavkada 'Rad etildi' umuman ko'rinmaydi", () => {
  // Xatoning o'zi: /task/68 da ish bajarilib, baholangan bo'lsa ham
  // yo'lakchada "Rad etildi" turardi — o'tilgan bosqich sifatida yashil bo'lib.
  assert.ok(!keys('done').includes('rejected'));
  assert.ok(!keys('done', true, 5).includes('rejected'));
  assert.ok(!keys('todo').includes('rejected'));
  assert.ok(!keys('in_progress').includes('rejected'));
});

test('baxtli yo\'l: Ochiq -> Jarayonda -> Bajarildi -> Baholandi', () => {
  assert.deepEqual(keys('todo'), ['todo', 'in_progress', 'done', 'rated']);

  assert.equal(current('todo'), 'todo');
  assert.equal(current('in_progress'), 'in_progress');
  assert.equal(current('done'), 'done');
  assert.equal(current('done', false, 5), 'rated');
});

test("rad etilgan zayavkada 'Rad etildi' — oxirgi qadam", () => {
  for (const status of ['rejected', 'stopped', 'cancelled']) {
    const { steps, currentIndex } = buildTaskStepper(status, false, null);

    assert.deepEqual(steps.map((s) => s.key), ['todo', 'in_progress', 'rejected']);
    assert.equal(currentIndex, steps.length - 1, `${status}: yakuniy qadam bo'lishi kerak`);
    // "Bajarildi" va "Baholandi" rad etilgan zayavkada yo'q.
    assert.ok(!steps.some((s) => s.key === 'done' || s.key === 'rated'));
  }
});

test('baho faqat bajarilgan ishga tegishli', () => {
  // Ochiq zayavkada eski baho qolib ketgan bo'lsa ham oxiriga sakramaydi.
  assert.equal(current('in_progress', false, 5), 'in_progress');
  assert.equal(current('todo', false, 3), 'todo');
});

test('`completed` bayrog\'i ham bajarilgan deb hisoblanadi', () => {
  assert.equal(current('in progress', true, null), 'done');
  assert.equal(current(null, true, 4), 'rated');
});

test("nomalum yoki bo'sh holat boshlanishda turadi", () => {
  assert.equal(current(null), 'todo');
  assert.equal(current(''), 'todo');
  assert.equal(current('IN PROGRESS'), 'in_progress');
});
