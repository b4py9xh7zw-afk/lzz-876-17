<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">我的成绩</h1>
    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>
    <div v-else-if="records.length === 0" class="text-center py-8 text-gray-500">
      暂无考试记录
    </div>
    <div v-else class="bg-white shadow overflow-hidden sm:rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">试卷</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">得分</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">复核状态</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">考试时间</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="record in records" :key="record.id">
            <td class="px-6 py-4 whitespace-nowrap">{{ record.exam_paper?.title }}</td>
            <td class="px-6 py-4 whitespace-nowrap font-bold" :class="{'text-green-600': record.score >= 60, 'text-red-600': record.score < 60}">{{ record.score }} 分</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                {{ record.status === 'graded' ? '已评分' : record.status }}
              </span>
            </td>
            <td class="px-6 py-4">
              <div v-if="!record.appeals || record.appeals.length === 0" class="text-xs text-gray-400">无申诉</div>
              <div v-else class="flex flex-wrap gap-1">
                <span
                  v-for="appeal in record.appeals"
                  :key="appeal.id"
                  class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full"
                  :class="appealStatusBadgeClass(appeal.status)"
                >
                  {{ appealStatusLabel(appeal.status) }}<template v-if="appeal.status === 'completed' && appeal.result">·{{ appealResultLabel(appeal.result) }}</template>
                </span>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ new Date(record.created_at).toLocaleString() }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <button
                v-if="record.status === 'graded'"
                @click="openAppealModal(record)"
                class="text-indigo-600 hover:text-indigo-900 font-medium mr-3"
              >申诉</button>
              <button
                v-if="record.appeals && record.appeals.length > 0"
                @click="openTrailModal(record)"
                class="text-emerald-600 hover:text-emerald-900 font-medium"
              >申诉记录</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 提交申诉模态框 -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showAppealModal" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
          <div class="fixed inset-0 bg-gray-600/75 backdrop-blur-sm transition-opacity" @click="closeAppealModal"></div>
          <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white shadow-2xl transition-all border border-gray-100">
              <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">提交成绩申诉</h3>
                <button @click="closeAppealModal" class="text-gray-400 hover:text-gray-500 bg-white rounded-full p-1 hover:bg-gray-100 transition-colors">
                  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <div class="px-6 py-6 max-h-[calc(100vh-14rem)] overflow-y-auto">
                <div v-if="appealFormLoading" class="text-center py-8">
                  <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
                </div>
                <div v-else class="space-y-5">
                  <div class="bg-indigo-50/60 rounded-lg px-4 py-3 text-sm text-gray-700">
                    试卷：<span class="font-semibold">{{ appealRecord?.exam_paper?.title }}</span>
                    <span class="ml-4">当前得分：<span class="font-semibold text-indigo-600">{{ appealRecord?.score }} 分</span></span>
                  </div>
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">申诉题目 <span class="text-red-500">*</span></label>
                    <select v-model="appealForm.question_id" class="input-base">
                      <option value="" disabled>请选择题目</option>
                      <option v-for="q in appealQuestions" :key="q.id" :value="q.id">
                        {{ q.title.length > 40 ? q.title.slice(0, 40) + '…' : q.title }}（满分 {{ q.full_score }} 分，我得 {{ q.my_score }} 分）
                      </option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">申诉类型 <span class="text-red-500">*</span></label>
                    <div class="flex gap-3">
                      <label
                        v-for="(label, type) in appealTypeLabels"
                        :key="type"
                        class="flex-1 flex items-center justify-center px-3 py-2.5 border rounded-lg cursor-pointer text-sm font-medium transition-all"
                        :class="appealForm.appeal_type === type ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                      >
                        <input type="radio" :value="type" v-model="appealForm.appeal_type" class="sr-only">
                        {{ label }}
                      </label>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">申诉原因 <span class="text-red-500">*</span></label>
                    <textarea v-model="appealForm.reason" rows="4" maxlength="1000" class="input-base resize-y" placeholder="请详细说明申诉原因（1000字以内）"></textarea>
                  </div>
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">上传证据 <span class="text-red-500">*</span></label>
                    <input
                      type="file"
                      @change="onEvidenceChange"
                      accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
                      class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    >
                    <p class="mt-1 text-xs text-gray-400">支持 jpg、png、gif、webp、pdf，大小不超过 5MB</p>
                    <p v-if="appealForm.evidence" class="mt-1 text-xs text-emerald-600">已选择：{{ appealForm.evidence.name }}</p>
                  </div>
                </div>
              </div>
              <div class="bg-gray-50/50 px-6 py-4 flex flex-row-reverse gap-3">
                <button
                  @click="submitAppeal"
                  :disabled="appealSubmitting || appealFormLoading"
                  class="inline-flex justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 min-w-[90px]"
                >{{ appealSubmitting ? '提交中...' : '提交申诉' }}</button>
                <button @click="closeAppealModal" class="inline-flex justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">取消</button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- 申诉记录/复核轨迹模态框 -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showTrailModal" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
          <div class="fixed inset-0 bg-gray-600/75 backdrop-blur-sm transition-opacity" @click="showTrailModal = false"></div>
          <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-3xl transform overflow-hidden rounded-2xl bg-white shadow-2xl transition-all border border-gray-100">
              <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">申诉记录与复核轨迹</h3>
                <button @click="showTrailModal = false" class="text-gray-400 hover:text-gray-500 bg-white rounded-full p-1 hover:bg-gray-100 transition-colors">
                  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <div class="px-6 py-6 max-h-[calc(100vh-14rem)] overflow-y-auto space-y-5">
                <div v-if="trailAppeals.length === 0" class="text-center py-8 text-gray-500">暂无申诉记录</div>
                <div v-for="appeal in trailAppeals" :key="appeal.id" class="border border-gray-200 rounded-xl p-5 space-y-4">
                  <div class="flex items-start justify-between">
                    <div>
                      <div class="font-semibold text-gray-900">{{ appeal.question?.title }}</div>
                      <div class="mt-1 text-xs text-gray-500">
                        类型：{{ appealTypeLabels[appeal.appeal_type] || appeal.appeal_type }}
                        <span class="mx-2">·</span>提交于 {{ new Date(appeal.created_at).toLocaleString() }}
                      </div>
                    </div>
                    <span class="px-2 py-1 text-xs font-semibold rounded-full whitespace-nowrap" :class="appealStatusBadgeClass(appeal.status)">
                      {{ appealStatusLabel(appeal.status) }}
                    </span>
                  </div>
                  <div class="text-sm text-gray-700 bg-gray-50 rounded-lg p-3">
                    <span class="font-medium text-gray-500">申诉原因：</span>{{ appeal.reason }}
                  </div>
                  <div class="flex items-center gap-4 text-sm">
                    <button @click="downloadEvidence(appeal)" class="text-indigo-600 hover:text-indigo-800 font-medium">
                      下载证据{{ appeal.evidence_name ? `（${appeal.evidence_name}）` : '' }}
                    </button>
                    <div v-if="appeal.status === 'completed'" class="text-gray-600">
                      复核结果：<span class="font-semibold">{{ appealResultLabel(appeal.result) }}</span>
                      <span v-if="Number(appeal.score_delta) !== 0" class="ml-2" :class="Number(appeal.score_delta) > 0 ? 'text-emerald-600' : 'text-red-600'">
                        {{ Number(appeal.score_delta) > 0 ? '+' : '' }}{{ appeal.score_delta }} 分
                      </span>
                    </div>
                  </div>
                  <!-- 复核轨迹 -->
                  <div>
                    <div class="text-xs font-semibold text-gray-500 uppercase mb-2">处理轨迹</div>
                    <div v-if="!appeal.reviews || appeal.reviews.length === 0" class="text-sm text-gray-400">暂无处理记录，等待老师复核</div>
                    <ol v-else class="relative border-l border-gray-200 ml-2 space-y-4">
                      <li v-for="review in appeal.reviews" :key="review.id" class="ml-4">
                        <div class="absolute w-3 h-3 bg-indigo-200 rounded-full -left-1.5 border border-white"></div>
                        <div class="text-sm">
                          <span class="font-semibold text-gray-900">{{ review.handler?.real_name || review.handler?.username || '处理人' }}</span>
                          <span class="text-gray-400 text-xs ml-1">({{ roleLabel(review.handler?.role) }})</span>
                          <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full" :class="reviewActionBadgeClass(review.action)">{{ reviewActionLabel(review.action) }}</span>
                          <span v-if="Number(review.score_delta) !== 0" class="ml-1 text-xs" :class="Number(review.score_delta) > 0 ? 'text-emerald-600' : 'text-red-600'">
                            {{ Number(review.score_delta) > 0 ? '+' : '' }}{{ review.score_delta }} 分
                          </span>
                        </div>
                        <div class="mt-1 text-sm text-gray-600 bg-gray-50 rounded-lg p-2.5">{{ review.opinion }}</div>
                        <div class="mt-1 text-xs text-gray-400">{{ new Date(review.created_at).toLocaleString() }}</div>
                      </li>
                    </ol>
                  </div>
                </div>
              </div>
              <div class="bg-gray-50/50 px-6 py-4 flex flex-row-reverse">
                <button @click="showTrailModal = false" class="inline-flex justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">关闭</button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'
import { useToast } from '../../composables/useToast'
import { useModal } from '../../composables/useModal'

const { success: toastSuccess } = useToast()
const { alert } = useModal()

const records = ref([])
const loading = ref(true)

const appealTypeLabels = {
  score: '分数异议',
  grading: '判题异议',
  anomaly: '异常标记异议'
}

// 申诉提交
const showAppealModal = ref(false)
const appealRecord = ref(null)
const appealQuestions = ref([])
const appealFormLoading = ref(false)
const appealSubmitting = ref(false)
const appealForm = ref({
  question_id: '',
  appeal_type: 'score',
  reason: '',
  evidence: null
})

// 申诉记录轨迹
const showTrailModal = ref(false)
const trailAppeals = ref([])

onMounted(async () => {
  await loadRecords()
})

const loadRecords = async () => {
  loading.value = true
  try {
    const response = await api.get('/exams/records')
    records.value = response.data.records.data
  } catch (e) {
    console.error('Failed to fetch records:', e)
  } finally {
    loading.value = false
  }
}

const openAppealModal = async (record) => {
  appealRecord.value = record
  appealForm.value = { question_id: '', appeal_type: 'score', reason: '', evidence: null }
  appealQuestions.value = []
  showAppealModal.value = true
  appealFormLoading.value = true
  try {
    const response = await api.get(`/exams/records/${record.id}`)
    const detail = response.data.record
    const answersMap = {}
    ;(detail.answers || []).forEach(a => { answersMap[a.question_id] = a })
    appealQuestions.value = (detail.exam_paper?.questions || []).map(q => ({
      id: q.id,
      title: q.title,
      full_score: q.pivot?.score ?? q.score,
      my_score: answersMap[q.id]?.score ?? 0
    }))
  } catch (e) {
    showAppealModal.value = false
    alert('获取试卷题目失败，请稍后重试', '加载失败', 'error')
  } finally {
    appealFormLoading.value = false
  }
}

const closeAppealModal = () => {
  if (appealSubmitting.value) return
  showAppealModal.value = false
}

const onEvidenceChange = (event) => {
  const file = event.target.files?.[0] || null
  if (file && file.size > 5 * 1024 * 1024) {
    alert('证据文件不能超过5MB', '文件过大', 'warning')
    event.target.value = ''
    appealForm.value.evidence = null
    return
  }
  appealForm.value.evidence = file
}

const submitAppeal = async () => {
  if (!appealForm.value.question_id) {
    alert('请选择申诉题目', '提示', 'warning')
    return
  }
  if (!appealForm.value.reason.trim()) {
    alert('请填写申诉原因', '提示', 'warning')
    return
  }
  if (!appealForm.value.evidence) {
    alert('请上传申诉证据', '提示', 'warning')
    return
  }
  appealSubmitting.value = true
  try {
    const formData = new FormData()
    formData.append('exam_record_id', appealRecord.value.id)
    formData.append('question_id', appealForm.value.question_id)
    formData.append('appeal_type', appealForm.value.appeal_type)
    formData.append('reason', appealForm.value.reason.trim())
    formData.append('evidence', appealForm.value.evidence)
    await api.post('/appeals', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    showAppealModal.value = false
    toastSuccess('申诉提交成功，请等待老师复核')
    await loadRecords()
  } catch (e) {
    const errors = e.response?.data?.errors
    const firstError = errors ? Object.values(errors)[0]?.[0] : null
    alert(firstError || e.response?.data?.message || '申诉提交失败', '提交失败', 'error')
  } finally {
    appealSubmitting.value = false
  }
}

const openTrailModal = async (record) => {
  trailAppeals.value = []
  showTrailModal.value = true
  try {
    const response = await api.get('/appeals/my', { params: { per_page: 100 } })
    const all = response.data.appeals.data || []
    trailAppeals.value = all.filter(a => a.exam_record_id === record.id)
  } catch (e) {
    console.error('Failed to fetch appeals:', e)
  }
}

const downloadEvidence = async (appeal) => {
  try {
    const response = await api.get(`/appeals/${appeal.id}/evidence`, { responseType: 'blob' })
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = appeal.evidence_name || 'evidence'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch (e) {
    alert('证据下载失败', '下载失败', 'error')
  }
}

const appealStatusLabel = (status) => ({
  pending: '申诉待复核',
  forwarded: '已转教务',
  completed: '复核完成'
}[status] || status)

const appealStatusBadgeClass = (status) => ({
  pending: 'bg-yellow-100 text-yellow-800',
  forwarded: 'bg-blue-100 text-blue-800',
  completed: 'bg-green-100 text-green-800'
}[status] || 'bg-gray-100 text-gray-800')

const appealResultLabel = (result) => ({
  maintain: '维持原判',
  add_score: '加分',
  reduce_score: '减分'
}[result] || result || '')

const reviewActionLabel = (action) => ({
  maintain: '维持原判',
  add_score: '加分',
  reduce_score: '减分',
  forward: '转给教务'
}[action] || action)

const reviewActionBadgeClass = (action) => ({
  maintain: 'bg-gray-100 text-gray-700',
  add_score: 'bg-emerald-100 text-emerald-700',
  reduce_score: 'bg-red-100 text-red-700',
  forward: 'bg-blue-100 text-blue-700'
}[action] || 'bg-gray-100 text-gray-700')

const roleLabel = (role) => ({
  admin: '教务',
  teacher: '教师',
  student: '学生'
}[role] || role || '')
</script>
