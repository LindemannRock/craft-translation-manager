import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';

const pluginRoot = path.resolve(import.meta.dirname, '../..');
const templatePath = path.join(pluginRoot, 'src/templates/translations/index.twig');

function editorClasses() {
    const source = readFileSync(templatePath, 'utf8');
    const start = source.indexOf('        class TranslationTracker');
    const end = source.indexOf('        const tracker = new TranslationTracker()', start);
    assert.notEqual(start, -1, 'TranslationTracker runtime is missing.');
    assert.notEqual(end, -1, 'Translation editor runtime boundary is missing.');

    const context = {
        document: {
            querySelector: () => null,
            querySelectorAll: () => [],
        },
    };
    vm.createContext(context);
    vm.runInContext(
        `${source.slice(start, end)}\nglobalThis.EditorClasses = {TranslationTracker, TranslationSaveCoordinator};`,
        context,
    );

    return context.EditorClasses;
}

function deferred() {
    let resolve;
    const promise = new Promise((promiseResolve) => {
        resolve = promiseResolve;
    });
    return {promise, resolve};
}

function fixture() {
    const {TranslationTracker, TranslationSaveCoordinator} = editorClasses();
    const tracker = new TranslationTracker();
    const currentValues = new Map([['1', 'original']]);
    const requests = [];
    tracker.originalValues.set('1', 'original');
    const coordinator = new TranslationSaveCoordinator(tracker, (id, submittedValue) => {
        const response = deferred();
        requests.push({id, submittedValue, response});
        return response.promise.then((success) => ({
            success,
            currentValue: currentValues.get(id) ?? '',
        }));
    });

    const edit = (value) => {
        currentValues.set('1', value);
        tracker.markChanged('1', value);
    };

    return {tracker, coordinator, currentValues, requests, edit};
}

async function nextTurn() {
    await Promise.resolve();
    await Promise.resolve();
}

test('an older response preserves a newer unsaved browser edit and navigation protection', async () => {
    const current = fixture();
    current.edit('A');
    const savingA = current.coordinator.save('1', 'A');
    current.edit('B');

    current.requests[0].response.resolve(true);
    assert.equal(await savingA, true);
    assert.equal(current.tracker.unsavedChanges.get('1'), 'B');
    assert.equal(current.tracker.originalValues.get('1'), 'A');
    assert.equal(current.tracker.hasUnsavedChanges(), true);
});

test('a second blur queues the newer value until the active request completes', async () => {
    const current = fixture();
    current.edit('A');
    const savingA = current.coordinator.save('1', 'A');
    current.edit('B');
    const savingB = current.coordinator.save('1', 'B');

    assert.equal(savingB, savingA);
    assert.deepEqual(current.requests.map(({submittedValue}) => submittedValue), ['A']);
    current.requests[0].response.resolve(true);
    await nextTurn();
    assert.deepEqual(current.requests.map(({submittedValue}) => submittedValue), ['A', 'B']);

    current.requests[1].response.resolve(true);
    assert.equal(await savingB, true);
    assert.equal(current.tracker.hasUnsavedChanges(), false);
    assert.equal(current.tracker.originalValues.get('1'), 'B');
});

test('a failed older request retains the newer edit without issuing its queued save', async () => {
    const current = fixture();
    current.edit('A');
    const savingA = current.coordinator.save('1', 'A');
    current.edit('B');
    current.coordinator.save('1', 'B');

    current.requests[0].response.resolve(false);
    assert.equal(await savingA, false);
    assert.deepEqual(current.requests.map(({submittedValue}) => submittedValue), ['A']);
    assert.equal(current.tracker.unsavedChanges.get('1'), 'B');
    assert.equal(current.tracker.originalValues.get('1'), 'original');
    assert.equal(current.tracker.hasUnsavedChanges(), true);
});

test('a repeated save request does not duplicate a value when no newer edit exists', async () => {
    const current = fixture();
    current.edit('A');
    const firstSave = current.coordinator.save('1', 'A');
    const secondSave = current.coordinator.save('1', 'A');

    current.requests[0].response.resolve(true);
    assert.equal(await firstSave, true);
    assert.equal(await secondSave, true);
    assert.deepEqual(current.requests.map(({submittedValue}) => submittedValue), ['A']);
    assert.equal(current.tracker.hasUnsavedChanges(), false);
});

test('the runtime navigation guard still reads the version-aware tracker', () => {
    const source = readFileSync(templatePath, 'utf8');
    assert.match(source, /beforeunload[\s\S]*?tracker\.hasUnsavedChanges\(\)/);
});
