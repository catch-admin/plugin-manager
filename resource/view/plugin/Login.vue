<template>
  <el-dialog v-model="visible" title="登录插件市场" width="400px" :close-on-click-modal="false" @close="handleClose">
    <el-form :model="form" label-width="80px" ref="formRef" @submit.prevent="handleSubmit">
      <el-form-item
        label="邮箱"
        prop="email"
        :rules="[
          { required: true, message: '请输入邮箱', trigger: 'blur' },
          { type: 'email', message: '请输入正确的邮箱格式', trigger: 'blur' }
        ]"
      >
        <el-input v-model="form.email" placeholder="请输入邮箱" />
      </el-form-item>
      <el-form-item
        label="密码"
        prop="password"
        :rules="[{ required: true, message: '请输入密码', trigger: 'blur' }]"
      >
        <el-input v-model="form.password" type="password" placeholder="请输入密码" show-password />
      </el-form-item>
    </el-form>
    <template #footer>
      <span class="dialog-footer">
        <el-button @click="handleClose">取消</el-button>
        <el-button type="primary" :loading="loading" @click="handleSubmit">登录</el-button>
      </span>
    </template>
  </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive, watch } from 'vue'
import type { FormInstance } from 'element-plus'

interface Props {
  modelValue: boolean
  loading?: boolean
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
  (e: 'submit', form: { email: string; password: string }): void
}

const props = withDefaults(defineProps<Props>(), {
  loading: false
})

const emit = defineEmits<Emits>()

const visible = ref(false)
const formRef = ref<FormInstance>()
const form = reactive({
  email: '',
  password: ''
})

// 监听外部值变化
watch(() => props.modelValue, (val) => {
  visible.value = val
})

// 监听内部值变化
watch(visible, (val) => {
  emit('update:modelValue', val)
})

// 关闭弹窗
const handleClose = () => {
  visible.value = false
  // 清空表单
  form.email = ''
  form.password = ''
  formRef.value?.resetFields()
}

// 提交表单
const handleSubmit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate((valid) => {
    if (valid) {
      emit('submit', { ...form })
    }
  })
}
</script>
